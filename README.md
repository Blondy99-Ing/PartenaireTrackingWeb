================================================================================
NOTE DE MIGRATION FUTURE — Dashboard Partenaire
Proxym / Fleetra — Architecture actuelle : users + partner_id (relation récursive)
================================================================================

Cette note identifie les points qui poseront problème lors d'une future migration
vers une architecture multi-tenant propre avec un modèle Partner dédié.
Aucun changement n'est demandé maintenant.

--------------------------------------------------------------------------------
1. POINTS DE RUPTURE FUTURS (à garder en tête)
--------------------------------------------------------------------------------

A. La relation récursive users.partner_id
   ─────────────────────────────────────
   Actuellement :
     - partenaire = User avec partner_id = NULL
     - chauffeur  = User avec partner_id = id_partenaire

   Problème futur :
     - Un User partenaire et un User chauffeur partagent le même modèle et la
       même table. Il n'y a pas de contrainte forte pour distinguer les deux.
     - La logique métier est dispersée dans le code (->where('partner_id', null)
       ou ->where('partner_id', $id)).
     - Si un futur "partenaire" a lui-même un partner_id (ex: sous-partenaire),
       la logique casse.

   Préparation recommandée (sans casser l'existant) :
     - Ajouter un scope User::scopePartners() et User::scopeDrivers($partnerId)
       pour centraliser le filtre au niveau du modèle User.
     - Cela permettra de faire la migration sans chercher tous les ->where('partner_id').

   Exemple à ajouter dans User.php (inoffensif maintenant) :
     public function scopePartners(Builder $query): Builder {
         return $query->whereNull('partner_id');
     }
     public function scopeDriversOf(Builder $query, int $partnerId): Builder {
         return $query->where('partner_id', $partnerId);
     }


B. DashboardCacheService — clés Redis dash:p:{userId}:*
   ─────────────────────────────────────────────────────
   Actuellement : {userId} = auth()->id() du partenaire (un User)

   Problème futur :
     - Si le modèle Partner est créé avec ses propres IDs, les clés Redis
       devront changer de dash:p:{userId} vers dash:p:{partnerId}.
     - Les caches existants deviendront orphelins.

   Préparation recommandée :
     - Ne pas changer maintenant.
     - Lors de la migration : créer une commande artisan de flush sélectif
       et migrer les clés en one-shot.


C. AssociationUserVoiture — user_id = id du partenaire (User)
   ────────────────────────────────────────────────────────────
   Actuellement : user_id référence users.id (le partenaire)

   Problème futur :
     - Si Partner devient un modèle séparé, cette clé devra devenir partner_id
       et pointer vers partners.id.

   Préparation recommandée :
     - Ajouter une colonne partner_id nullable à AssociationUserVoiture
       (en parallèle de user_id, en double pendant la transition).
     - Remplir partner_id lors de la création du modèle Partner via migration.


D. TrackingWebhookController — partnerIdsFromVoitureIds / partnerIdsFromMacs
   ──────────────────────────────────────────────────────────────────────────
   Ces méthodes lisent AssociationUserVoiture.user_id et supposent que c'est
   un partenaire. Si des chauffeurs ont aussi des entrées dans cette table,
   cela cassera le routing.

   Préparation recommandée :
     - Ajouter un WHERE sur users.partner_id IS NULL lors de la résolution
       pour garantir qu'on remonte bien vers un partenaire.
     - Cela peut être fait maintenant sans casser quoi que ce soit.

   Code à ajouter dans partnerIdsFromVoitureIds() (sûr maintenant) :
     return AssociationUserVoiture::query()
         ->whereIn('voiture_id', $voitureIds)
         ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
         ->whereNull('users.partner_id')   // garantit que c'est bien un partenaire
         ->pluck('association_user_voitures.user_id')
         ->map(fn ($x) => (int) $x)
         ->unique()
         ->values()
         ->all();


--------------------------------------------------------------------------------
2. AMÉLIORATIONS COMPATIBLES (peuvent être faites maintenant, sans casser)
--------------------------------------------------------------------------------

A. Scopes User — centraliser les filtres partenaire/chauffeur
   (voir section 1.A ci-dessus)

B. WHERE users.partner_id IS NULL dans le Webhook
   (voir section 1.D ci-dessus)
   Évite qu'un chauffeur ayant malencontreusement une entrée dans
   association_user_voitures déclenche une mise à jour de cache partenaire.

C. TTL différencié par type de données
   Actuellement ttlFleet = ttlVehicleIds = 600s. Considérer :
     - ttlVehicleIds : 60s (données critiques pour le filtrage)
     - ttlFleet hash : 600s (données de position, reconstruites souvent)
     - ttlStats : 900s (données agrégées, moins critiques)

D. Index MySQL manquant (à vérifier)
   La requête MAX(id) GROUP BY mac_id_gps dans rebuildFleet() peut être lente
   sur une grande table locations sans index sur mac_id_gps.
   Vérifier : SHOW INDEX FROM locations;
   Ajouter si manquant : ALTER TABLE locations ADD INDEX idx_mac_id_gps (mac_id_gps);
   Et : ALTER TABLE locations ADD INDEX idx_mac_id_created (mac_id_gps, id);

E. Logging structuré des accès partenaire
   Ajouter un log dans dashboardStream() au démarrage :
     logger()->info('[SSE] partner connected', ['partner_id' => $partnerId, 'ip' => request()->ip()]);
   Utile pour le monitoring et le debugging en production.


--------------------------------------------------------------------------------
3. CHEMIN DE MIGRATION VERS MODÈLE PARTNER (futur, quand vous serez prêts)
--------------------------------------------------------------------------------

Étape 1 : Créer la table partners avec les colonnes nécessaires (name, slug, etc.)
Étape 2 : Pour chaque User partenaire (partner_id IS NULL), créer un Partner
          et stocker owner_user_id = user.id
Étape 3 : Ajouter partner_id en double dans AssociationUserVoiture et AssociationChauffeurVoiturePartner
Étape 4 : Faire pointer DashboardCacheService sur partner.id plutôt que user.id
          (changer $partnerId = auth()->id() par $partnerId = auth()->user()->ownedPartner()->id)
Étape 5 : Migrer les clés Redis en one-shot (flush + rebuild)
Étape 6 : Supprimer les anciennes colonnes user_id dans les tables d'association

Cette migration peut se faire progressivement sans downtime.

================================================================================
