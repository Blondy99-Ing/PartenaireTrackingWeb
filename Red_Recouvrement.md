# README — Module Recouvrement Lease & Coupure Automatique

## 1. Objectif du module

Le module Recouvrement Lease permet de gérer les échéances de paiement des contrats et sous-contrats liés aux véhicules, puis de déclencher automatiquement une coupure sécurisée du véhicule lorsqu’un paiement reste impayé.

La coupure automatique n’est jamais déclenchée directement sur un véhicule de façon globale. Elle dépend toujours d’un élément précis :

```txt
Véhicule + Contrat/Sous-contrat + Échéance impayée + Règle active
```

Un véhicule peut donc être coupé à cause :

* du contrat principal ;
* d’un sous-contrat ;
* de plusieurs contrats/sous-contrats indépendants associés au même véhicule.

---

## 2. Tables principales

### 2.1 `lease_contract_links`

Cette table représente le lien local entre la plateforme Tracking et les contrats créés dans le système de recouvrement.

Elle contient les contrats et sous-contrats réellement associés aux véhicules.

Rôle :

* identifier le contrat principal ;
* identifier les sous-contrats ;
* relier un contrat/sous-contrat à un véhicule ;
* servir de base pour appliquer une règle de coupure spécifique.

Exemple :

```txt
Contrat principal Moto
Sous-contrat Téléphone
Sous-contrat Assurance
```

Chaque ligne peut avoir sa propre règle dans `lease_cutoff_contract_rules`.

---

### 2.2 `lease_cutoff_default_rules`

Cette table contient les règles de coupure par défaut par type de contrat.

Elle sert de modèle.

Exemple :

```txt
Type Moto       → coupure à 12h00
Type Téléphone  → coupure à 17h00
Type Assurance  → coupure à 18h00
```

Important :

Une règle par défaut ne coupe jamais directement un véhicule. Elle doit d’abord être appliquée ou copiée sur un contrat/sous-contrat réel dans `lease_cutoff_contract_rules`.

---

### 2.3 `lease_cutoff_contract_rules`

Cette table contient les règles réellement applicables à un contrat ou sous-contrat précis.

C’est la table principale pour savoir si un contrat peut déclencher une coupure.

Elle est reliée à `lease_contract_links` via :

```txt
lease_cutoff_contract_rules.contract_link_id = lease_contract_links.id
```

Champs importants :

```txt
contract_link_id
is_enabled
cutoff_time
active_days
grace_minutes
only_when_stopped
notify_before_cutoff
```

Interprétation :

* `is_enabled = true` : la règle peut déclencher une coupure.
* `is_enabled = false` : aucune coupure ne doit être planifiée pour ce contrat/sous-contrat.
* `cutoff_time` : heure prévue de coupure.
* `grace_minutes` : délai supplémentaire avant coupure.
* `active_days` : jours où la règle est applicable.
* `only_when_stopped` : sécurité imposant que le véhicule soit arrêté avant coupure.

---

### 2.4 `lease_cutoff_queue`

Cette table contient les coupures réellement planifiées.

Une ligne dans cette table signifie :

```txt
Une coupure est prévue pour une échéance impayée précise.
```

Une règle active ne suffit pas à dire qu’une coupure est planifiée.
La coupure est planifiée uniquement lorsqu’une ligne existe dans `lease_cutoff_queue`.

Champs importants :

```txt
partner_id
vehicle_id
contract_id
lease_id
lease_date_echeance
contract_link_id
contract_rule_id
status
scheduled_for
attempts
```

Statuts possibles :

```txt
PENDING
PROCESSING
COMMAND_SENT
WAITING_SECURITY
FAILED
CANCELLED_PAID
CANCELLED_FORGIVEN_BEFORE_CUT
CUT_OFF
```

---

### 2.5 `lease_cutoff_histories`

Cette table conserve l’historique des décisions et actions de coupure.

Elle permet de savoir :

* pourquoi une coupure a été planifiée ;
* si elle a été annulée ;
* si elle a été exécutée ;
* si un pardon a empêché la coupure ;
* si un pardon après coupure a relancé le véhicule.

Champs importants :

```txt
partner_id
vehicle_id
contract_id
lease_id
lease_date_echeance
contract_link_id
contract_rule_id
status
detected_at
scheduled_for
cutoff_executed_at
forgiven_at
forgiven_by_user_id
command_response
```

Cette table est aussi utilisée pour empêcher la replanification après un pardon avant coupure.

---

## 3. Règles par défaut

Les règles par défaut sont définies par type de contrat.

Exemple :

```txt
Moto       → coupure à 12h00
Téléphone  → coupure à 17h00
Assurance  → coupure à 18h00
```

Elles sont utiles pour éviter de configurer manuellement chaque contrat.

Cependant, elles ne s’appliquent pas directement au véhicule.
Elles doivent être copiées vers `lease_cutoff_contract_rules`.

---

## 4. Application des règles

À la création d’un contrat ou sous-contrat, l’interface peut proposer :

```txt
Appliquer la règle par défaut : Oui / Non
```

### Cas 1 : l’utilisateur choisit Oui

Le système cherche la règle par défaut du type de contrat, puis crée une règle spécifique dans :

```txt
lease_cutoff_contract_rules
```

Exemple :

```txt
Contrat Moto créé
Type = Moto
Règle par défaut Moto = 12h00
→ création d’une règle spécifique pour ce contrat à 12h00
```

### Cas 2 : l’utilisateur choisit Non

Aucune règle spécifique n’est créée.

Conséquence :

```txt
Le contrat existe, mais aucune coupure automatique ne sera planifiée pour lui.
```

### Cas 3 : l’utilisateur définit une règle personnalisée

La règle personnalisée est enregistrée directement dans :

```txt
lease_cutoff_contract_rules
```

Elle ne modifie pas la règle par défaut.

---

## 5. Règles par contrat et sous-contrat

Chaque contrat et chaque sous-contrat possède sa propre règle indépendante.

Exemple :

```txt
Contrat principal Moto
- Règle active
- Coupure à 12h00

Sous-contrat Téléphone
- Règle active
- Coupure à 17h00

Sous-contrat Assurance
- Règle inactive
- Aucune coupure
```

Le paiement du contrat Moto ne règle pas automatiquement le sous-contrat Téléphone.
Chaque échéance est analysée séparément.

---

## 6. Priorité des règles

La règle réellement utilisée est toujours celle présente dans :

```txt
lease_cutoff_contract_rules
```

Priorité logique :

```txt
1. Règle personnalisée du contrat/sous-contrat
2. Règle par défaut copiée sur le contrat/sous-contrat
3. Aucune règle
```

Important :

`lease_cutoff_default_rules` est seulement un modèle.
Elle ne doit pas être utilisée directement par le cron pour couper un véhicule.

---

## 7. Condition pour couper un véhicule

Un véhicule peut être coupé si toutes les conditions suivantes sont réunies :

```txt
1. Le lease est NON_PAYE
2. Le contrat/sous-contrat est identifié dans lease_contract_links
3. Une règle existe dans lease_cutoff_contract_rules
4. La règle est active : is_enabled = true
5. Le jour courant est autorisé
6. L’heure de coupure + délai de grâce est atteinte
7. Aucun pardon avant coupure n’existe
8. Le véhicule est arrêté
9. L’état GPS/moteur est fiable
```

Si une seule condition échoue, la coupure ne doit pas être exécutée.

---

## 8. Exemple complet

### Configuration

```txt
Véhicule : Moto A
Contrat principal : Moto
Sous-contrat : Téléphone
```

Règles :

```txt
Moto       → règle active, coupure à 12h00
Téléphone  → règle active, coupure à 17h00
```

### Cas 1 : Moto impayée à 12h00

```txt
Lease Moto = NON_PAYE
Règle Moto = active
Heure atteinte = oui
Véhicule arrêté = oui
```

Résultat :

```txt
Le véhicule est coupé à cause du contrat Moto.
```

### Cas 2 : Moto payée, Téléphone impayé

```txt
Lease Moto = PAYE
Lease Téléphone = NON_PAYE
Règle Téléphone = active
Heure 17h00 atteinte
```

Résultat :

```txt
Le véhicule est coupé à cause du sous-contrat Téléphone.
```

### Cas 3 : Téléphone impayé mais règle inactive

```txt
Lease Téléphone = NON_PAYE
Règle Téléphone is_enabled = false
```

Résultat :

```txt
Aucune coupure n’est planifiée.
```

### Cas 4 : Pardon avant coupure

```txt
Lease Téléphone = NON_PAYE
Règle active
Queue pas encore exécutée
Pardon accordé avant coupure
```

Résultat :

```txt
La coupure est annulée.
Le véhicule ne doit pas être coupé.
Le système doit empêcher toute replanification pour cette même échéance.
```

### Cas 5 : Pardon après coupure

```txt
Lease Moto = NON_PAYE
Coupure déjà confirmée
Pardon accordé après coupure
```

Résultat :

```txt
Le système envoie une commande de relance/rallumage du véhicule.
```

---

## 9. Pardon du non-paiement

Le pardon ne doit pas modifier le statut principal du paiement.
Il concerne uniquement la logique de coupure.

### Pardon avant coupure

Le pardon avant coupure doit :

```txt
1. Annuler la queue active si elle existe
2. Créer un historique CANCELLED_FORGIVEN_BEFORE_CUT
3. Conserver contract_link_id
4. Conserver lease_id
5. Conserver lease_date_echeance
6. Empêcher la replanification
```

Clé métier de verrouillage :

```txt
partner_id
vehicle_id
lease_id
contract_link_id
lease_date_echeance
```

### Pardon après coupure

Le pardon après coupure doit :

```txt
1. Vérifier que la coupure est confirmée
2. Envoyer une commande de relance
3. Mettre à jour l’historique
4. Ne pas considérer COMMAND_SENT comme coupure confirmée
```

Important :

```txt
COMMAND_SENT ≠ CUT_OFF
```

Une commande envoyée ne signifie pas forcément que le véhicule est réellement coupé.

---

## 10. Cron jobs

Deux commandes principales doivent tourner chaque minute.

### 10.1 Planification

```bash
php artisan lease:cutoff:plan
```

Rôle :

```txt
1. Lire les leases NON_PAYE du jour
2. Identifier le contrat/sous-contrat exact
3. Vérifier la règle contractuelle
4. Vérifier is_enabled
5. Vérifier heure, jours actifs et délai de grâce
6. Vérifier qu’il n’y a pas pardon avant coupure
7. Créer une queue dans lease_cutoff_queue
8. Créer ou mettre à jour l’historique
```

La commande ne doit traiter automatiquement que les échéances du jour courant.

---

### 10.2 Traitement

```bash
php artisan lease:cutoff:process
```

Rôle :

```txt
1. Lire les queues PENDING
2. Revérifier que le lease est toujours NON_PAYE
3. Annuler si payé
4. Annuler si pardon avant coupure
5. Revérifier que la règle existe encore
6. Revérifier que is_enabled = true
7. Vérifier la sécurité véhicule
8. Attendre si véhicule roule, offline ou état incertain
9. Envoyer la commande de coupure
10. Confirmer l’état moteur
11. Mettre à jour queue + historique
```

---

## 11. Sécurité de coupure

La coupure automatique doit toujours rester sécurisée.

La règle recommandée est :

```txt
only_when_stopped = true
```

Avant d’envoyer la commande, le système doit vérifier :

```txt
- vitesse véhicule ;
- statut GPS ;
- état moteur ;
- dernière remontée GPS ;
- fiabilité de la donnée.
```

Si le véhicule roule, est offline ou si l’état est incertain :

```txt
La coupure ne doit pas être envoyée.
La queue passe en attente sécurité.
```

---

## 12. Dashboard Lease

Le dashboard doit distinguer clairement :

```txt
Règles actives
Règles inactives
Contrats sans règle
Coupures réellement planifiées
Coupures exécutées
Coupures annulées
Pardons avant coupure
Pardons après coupure
Relances véhicule
```

Important :

```txt
Règle active ≠ coupure planifiée
```

Une coupure est planifiée uniquement si elle existe dans :

```txt
lease_cutoff_queue
```

---

## 13. Vue paiements

La page paiements doit afficher deux informations séparées :

### Statut paiement

Exemples :

```txt
PAYE
NON_PAYE
EN_RETARD
PARTIEL
```

### Statut coupure

Exemples :

```txt
Aucune règle
Règle inactive
Éligible à la coupure
Coupure planifiée
En attente sécurité
Commande envoyée
Coupure confirmée
Annulée car payé
Annulée par pardon
```

Le pardon ne doit pas remplacer le vrai statut paiement.

---

## 14. Vue historique

L’historique doit afficher :

```txt
Chauffeur
Véhicule
Contrat ou sous-contrat
Type de contrat
Date d’échéance
Règle utilisée
Heure prévue
Statut coupure
Pardon avant/après coupure
Commande envoyée
Réponse GPS
Date d’exécution
Date de relance
```

Cela permet de comprendre pourquoi un véhicule a été coupé ou non.

---

## 15. Règles importantes à respecter

### Règle 1

Une règle par défaut ne coupe jamais directement.

Elle sert seulement à générer une règle spécifique dans :

```txt
lease_cutoff_contract_rules
```

### Règle 2

Un sous-contrat est indépendant du contrat principal.

### Règle 3

`is_enabled = false` bloque la planification.

### Règle 4

Un pardon avant coupure empêche la coupure et doit empêcher la replanification.

### Règle 5

Un pardon après coupure doit relancer le véhicule uniquement si la coupure est confirmée.

### Règle 6

`COMMAND_SENT` ne doit pas être considéré comme coupure confirmée.

### Règle 7

La coupure doit toujours être sécurisée.

### Règle 8

La création de contrat ne doit pas être bloquante si l’application de la règle échoue.

---

## 16. Requête utile : récupérer les règles par contrat

```sql
SELECT
    lcl.id AS contract_link_id,
    lcl.contract_id,
    lcl.parent_contract_id,
    lcl.contract_type,
    lcl.vehicle_id,
    lccr.id AS rule_id,
    lccr.is_enabled,
    lccr.cutoff_time,
    lccr.active_days,
    lccr.grace_minutes,
    lccr.only_when_stopped
FROM lease_contract_links lcl
LEFT JOIN lease_cutoff_contract_rules lccr
    ON lccr.contract_link_id = lcl.id
ORDER BY lcl.vehicle_id, lcl.parent_contract_id, lcl.id;
```

---

## 17. Requête utile : récupérer les coupures planifiées du jour

```sql
SELECT
    q.id,
    q.partner_id,
    q.vehicle_id,
    q.contract_id,
    q.lease_id,
    q.lease_date_echeance,
    q.contract_link_id,
    q.contract_rule_id,
    q.status,
    q.scheduled_for
FROM lease_cutoff_queue q
WHERE DATE(q.scheduled_for) = CURDATE()
ORDER BY q.scheduled_for ASC;
```

---

## 18. Conclusion

La logique correcte du module est :

```txt
Recouvrement = paiement des échéances
Règle = configuration de coupure par contrat/sous-contrat
Queue = coupure réellement planifiée
Historique = preuve de décision/action
Pardon = exception contrôlée à la coupure
```

Le système doit toujours décider à partir du contrat ou sous-contrat exact, jamais uniquement à partir du véhicule ou d’un type général abstrait.
