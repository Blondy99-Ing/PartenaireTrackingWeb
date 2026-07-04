# Module Lease — règles de coupure par contrat/sous-contrat réel

## 1. Objectif

Ce correctif remplace la logique dangereuse `véhicule + type de contrat général` par une logique sûre :

> Un véhicule ne peut être coupé que si le contrat spécifique du chauffeur, ou le sous-contrat spécifique réellement associé à ce contrat, possède une règle de coupure active.

Le paramétrage en masse reste possible, mais il ne crée/modifie que les règles des contrats/sous-contrats réels sélectionnés.

## 2. Règles métier validées

### 2.1 Pas de règle spécifique = pas de coupure

Le système ne planifie aucune coupure si :

- le contrat précis n’a pas de règle active ;
- le sous-contrat précis n’a pas de règle active ;
- la règle est absente ;
- la règle est désactivée ;
- l’heure de coupure est absente ;
- le lease exact n’est plus confirmé NON_PAYE ;
- le véhicule roule ;
- le véhicule est offline ;
- l’état GPS est incertain ;
- le véhicule n’a pas de `mac_id_gps`.

### 2.2 Sous-contrats affichés uniquement s’ils sont associés

Exemple :

- Contrat X : Moto
- Sous-contrat associé : Téléphone

L’écran affiche seulement :

- Moto / contrat X
- Téléphone / sous-contrat associé au contrat X

Il ne doit pas afficher :

- Parapluie ;
- Casque ;
- autre type non associé.

### 2.3 Paramétrage en masse

Le paramétrage en masse agit sur les lignes visibles ou sélectionnées, mais chaque règle reste attachée à un `contract_link_id` réel.

Donc si on sélectionne :

- Contrat A : Moto + Téléphone
- Contrat B : Moto + Parapluie
- Contrat C : Moto seul

Le bulk peut modifier :

- Contrat A / Moto
- Contrat A / Téléphone
- Contrat B / Moto
- Contrat B / Parapluie
- Contrat C / Moto

Il ne crée pas :

- Contrat A / Parapluie ;
- Contrat C / Téléphone ;
- Contrat C / Parapluie.

## 3. Tables importantes

### 3.1 `lease_contract_links`

Table de liaison entre Recouvrement et Tracking.

Elle identifie :

- le partenaire ;
- le véhicule ;
- le chauffeur ;
- le contrat Recouvrement ;
- le parent si sous-contrat ;
- le type de contrat ;
- MAIN ou SUB.

Cette table est la base de la nouvelle logique.

### 3.2 `lease_cutoff_contract_rules`

Nouvelle table principale de règles métier.

Chaque ligne correspond à une règle spécifique pour un contrat ou sous-contrat réel.

Champs clés :

- `contract_link_id` : contrat/sous-contrat réel ;
- `source_contract_id` : ID contrat côté Recouvrement ;
- `source_parent_contract_id` : parent si sous-contrat ;
- `contract_kind` : MAIN ou SUB ;
- `is_enabled` : autorise ou non la coupure ;
- `cutoff_time` : heure de coupure ;
- `grace_days` : délai de grâce ;
- `only_when_stopped` : sécurité d’arrêt ;
- `notify_before_cutoff` : notification éventuelle.

### 3.3 `lease_cutoff_queue`

File d’attente de coupure.

Elle évite de couper immédiatement et permet :

- l’idempotence ;
- l’attente si véhicule en mouvement ;
- la vérification du paiement ;
- la vérification GPS ;
- la confirmation de commande.

Nouveaux champs :

- `contract_rule_id` ;
- `lease_date_echeance`.

### 3.4 `lease_cutoff_histories`

Historique métier et audit.

Nouveaux champs :

- `contract_rule_id` ;
- `lease_date_echeance`.

Nouveaux statuts possibles :

- `CANCELLED_RULE_MISSING` ;
- `CANCELLED_RULE_DISABLED`.

## 4. Fonctionnement cron

### 4.1 Planification

Commande :

```bash
php artisan lease:cutoff:plan --date=2026-05-10
```

Rôle :

1. appeler `/leases/?statut=NON_PAYE&date_echeance=YYYY-MM-DD` ;
2. lire chaque `lease_id + contrat_id` ;
3. trouver le `LeaseContractLink` exact ;
4. chercher une règle active dans `lease_cutoff_contract_rules` ;
5. vérifier l’heure ;
6. créer une queue et un historique ;
7. ne pas envoyer de commande GPS.

### 4.2 Traitement

Commande :

```bash
php artisan lease:cutoff:process
```

Rôle :

1. lire les queues actives ;
2. revérifier `lease_id + date_echeance` ;
3. annuler si le lease n’est plus NON_PAYE ;
4. revérifier la règle spécifique avant tout envoi GPS ;
5. vérifier `mac_id_gps` ;
6. vérifier l’état GPS ;
7. attendre si le véhicule roule, est offline ou incertain ;
8. envoyer la commande uniquement si le véhicule est arrêté ;
9. attendre confirmation ;
10. clôturer l’historique.

## 5. Installation des fichiers

Copier les fichiers du dossier `files/` à la racine du projet Laravel en respectant les chemins.

Puis exécuter :

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
```

Pour vérifier les routes :

```bash
php artisan route:list --path=lease
```

## 6. Commandes de test métier

Planifier une date :

```bash
php artisan lease:cutoff:plan --date=2026-05-12
```

Traiter la queue :

```bash
php artisan lease:cutoff:process
```

Tester l’idempotence :

```bash
php artisan lease:cutoff:plan --date=2026-05-10
php artisan lease:cutoff:plan --date=2026-05-10
```

Le deuxième lancement ne doit pas créer de doublon.

## 7. SQL de vérification

### Contrats et sous-contrats liés

```sql
SELECT id, partner_id, vehicle_id, driver_id, source_contract_id,
       source_parent_contract_id, contract_kind, type_contrat_label, status
FROM lease_contract_links
ORDER BY vehicle_id, source_parent_contract_id, source_contract_id;
```

### Règles spécifiques

```sql
SELECT id, partner_id, vehicle_id, driver_id, contract_link_id,
       source_contract_id, source_parent_contract_id, contract_kind,
       type_contrat_label, is_enabled, cutoff_time
FROM lease_cutoff_contract_rules
ORDER BY vehicle_id, source_parent_contract_id, source_contract_id;
```

### Détecter des règles orphelines

```sql
SELECT r.*
FROM lease_cutoff_contract_rules r
LEFT JOIN lease_contract_links l ON l.id = r.contract_link_id
WHERE l.id IS NULL;
```

### Queues actives

```sql
SELECT id, partner_id, vehicle_id, contract_id, lease_id,
       lease_date_echeance, contract_link_id, contract_rule_id,
       status, scheduled_for, next_check_at
FROM lease_cutoff_queue
WHERE status IN ('PENDING', 'WAITING_STOP', 'COMMAND_SENT')
ORDER BY scheduled_for DESC;
```

### Historiques récents

```sql
SELECT id, vehicle_id, contract_id, lease_id, lease_date_echeance,
       contract_kind, type_contrat_label, status, reason,
       scheduled_for, cutoff_requested_at, cutoff_executed_at
FROM lease_cutoff_histories
ORDER BY id DESC
LIMIT 50;
```

## 8. Variables utiles

```env
PARTNER_LEASE_API_BASE_URL=https://recouvrement.proxymgroup.com/api/v1
LEASE_AUTH_TOKEN_URL=...
LEASE_AUTH_CLIENT_ID=...
LEASE_AUTH_CLIENT_SECRET=...
LEASE_AUTH_USERNAME=...
LEASE_AUTH_PASSWORD=...
LEASE_AUTH_SCOPE="openid email profile"
LEASE_CUTOFF_DUE_DATE_OFFSET_DAYS=1
LEASE_CUTOFF_WAITING_DELAY_MINUTES=1
LEASE_CUTOFF_CONFIRM_DELAY_SECONDS=20
LEASE_CUTOFF_CONFIRM_MAX_CHECKS=6
GPS_MOVING_THRESHOLD=5
```

## 9. Points d’attention

- Les anciennes tables `lease_cutoff_rules` et `lease_cutoff_rule_contract_types` sont conservées pour compatibilité, mais elles ne doivent plus décider la coupure.
- La migration convertit les anciennes règles `véhicule + type` en règles spécifiques seulement pour les `lease_contract_links` existants.
- Après migration, il faut vérifier l’écran de paramétrage et désactiver les règles qui ne doivent pas couper.
- Si un sous-contrat n’a pas de ligne dans `lease_contract_links`, il ne déclenchera pas de coupure. C’est volontaire pour éviter les coupures ambiguës.

## 10. Anciennes actions depuis la page contrats

Les anciennes routes :

```text
POST contrats/cutoff-policy
POST contrats/bulk-cutoff-policy
```

sont conservées pour ne pas casser les liens existants, mais elles redirigent vers `lease/cutoff-rules` avec un message métier. Le paramétrage par véhicule + type général est désactivé.
