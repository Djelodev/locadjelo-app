# BC02 — Rendu 1 : Environnement de test

## Projet LocaDjelo — Titre professionnel ASD (RNCP 36061)

**Candidat** : Ange IRIE BI
**Date** : Avril 2026
**Formateur référent** : Max Fauquemberg

---

## 1. Introduction et contexte

### 1.1. Rappel du projet

Le projet LocaDjelo consiste à déployer, conteneuriser et superviser une application 
web Laravel de vente et location immobilière dans le cadre du titre professionnel 
Administrateur Système DevOps (ASD).

Après le BC01 (infrastructure cloud automatisée avec Terraform et Ansible), ce 
premier rendu du BC02 porte sur la mise en place de l'**environnement de test 
conteneurisé** pour l'application LocaDjelo.

### 1.2. Objectif du rendu

Ce document décrit la mise en œuvre d'un environnement de test avec Docker Compose 
qui reproduit fidèlement la production, incluant :

- La conteneurisation de l'application Laravel avec tous ses services (Nginx, 
  PHP-FPM, MySQL, Redis)
- La gestion des volumes persistants pour garantir la survie des données
- Les procédures de sauvegarde et de restauration automatisées

### 1.3. Compétences du BC02 couvertes dans ce rendu

| Compétence | Intitulé | Couverture |
|---|---|---|
| CP5 | Préparer un environnement de test | Totale |
| CP6 | Gérer le stockage des données | Totale |
| CP7 | Gérer des containers | Partielle (Docker Compose) |
| CP8 | Automatiser la mise en production | À venir (rendus suivants) |

### 1.4. Stack technique utilisée

- **Conteneurisation** : Docker 29.3.1 + Docker Compose
- **Application** : Laravel (PHP 7.4 FPM sur Alpine Linux)
- **Serveur web** : Nginx Alpine
- **Base de données** : MySQL 8.0
- **Cache** : Redis Alpine
- **Sauvegardes** : mysqldump + gzip + cron
- **Environnement dev** : WSL Ubuntu 24.04 sur Windows


---

## 2. Architecture de l'environnement de test

### 2.1. Vue d'ensemble

L'environnement de test est composé de **4 containers Docker orchestrés par Docker 
Compose**, communiquant entre eux via un réseau interne isolé. Cette architecture 
reproduit fidèlement la production pour permettre de tester l'application dans des 
conditions réalistes.

### 2.2. Schéma d'architecture

┌─────────────────────────────────────────────────────────────┐
│              Docker Compose — locadjelo_network              │
│                                                              │
│  ┌──────────────┐    ┌──────────────┐   ┌──────────────┐   │
│  │   nginx      │───▶│     app      │──▶│    mysql     │   │
│  │  (port 80)   │    │  (PHP-FPM)   │   │  (MySQL 8.0) │   │
│  │              │    │   Laravel    │   │              │   │
│  └──────────────┘    └──────────────┘   └──────────────┘   │
│         ▲                   │                   │           │
│         │                   ▼                   ▼           │
│         │            ┌──────────────┐    [Volume mysql_data]│
│         │            │    redis     │                       │
│         │            │   (cache)    │                       │
│         │            └──────────────┘                       │
│         │                   │                               │
│         │                   ▼                               │
│         │            [Volume redis_data]                    │
└─────────┼───────────────────────────────────────────────────┘
│
▼
localhost:8080
(Développeur/Testeur)

### 2.3. Rôle de chaque service

| Service | Image | Rôle | Port |
|---|---|---|---|
| nginx | nginx:alpine | Reverse proxy et serveur web | 8080 (externe) |
| app | Image personnalisée (PHP 7.4-fpm) | Exécution du code Laravel | 9000 (interne) |
| mysql | mysql:8.0 | Base de données relationnelle | 3307 (externe) |
| redis | redis:alpine | Cache et sessions | 6379 (interne) |

### 2.4. Communication entre services

Les containers communiquent par leur **nom de service** grâce au DNS interne de 
Docker. Par exemple :

- Nginx transmet les requêtes PHP vers `app:9000`
- Laravel se connecte à MySQL via `mysql:3306`
- Laravel utilise Redis via `redis:6379`

Cette approche évite les configurations IP et permet de recréer l'environnement 
identiquement sur n'importe quelle machine.

### 2.5. Volumes persistants

Deux volumes nommés Docker garantissent la persistance des données :

| Volume | Emplacement dans le container | Contenu |
|---|---|---|
| mysql_data | /var/lib/mysql | Données de la base MySQL |
| redis_data | /data | Données du cache Redis |

Ces volumes survivent aux redémarrages et suppressions de containers, garantissant 
qu'aucune donnée n'est perdue lors des opérations de maintenance.

### 2.6. Isolation de l'environnement

L'environnement de test est totalement isolé du système hôte :

- **Réseau** : réseau Docker dédié (`locadjelo_network`), aucun port exposé hors 
  nécessité
- **Processus** : chaque service tourne dans son propre container
- **Utilisateur** : le service PHP tourne sous l'utilisateur non-root `laravel` 
  (bonne pratique de sécurité)
- **Système de fichiers** : isolation complète sauf pour les bind mounts explicites 
  (code source en dev)

  ---

## 3. Préparer un environnement de test (CP5)

### 3.1. Objectif

Le jury attend du candidat qu'il sache créer un environnement de test isolé, 
reproductible et conforme au cahier des charges, permettant de valider l'application 
avant sa mise en production.

### 3.2. Choix de Docker Compose

Le choix de Docker Compose comme outil d'orchestration locale se justifie par 
plusieurs critères :

- **Reproductibilité** : un seul fichier YAML décrit tout l'environnement. Deux 
  développeurs qui lancent `docker compose up` obtiennent un environnement identique
- **Isolation** : chaque service tourne dans son container, sans polluer le système 
  hôte
- **Proximité avec la production** : même architecture conteneurisée qu'en production 
  (containers), même stack (Nginx, PHP-FPM, MySQL, Redis)
- **Simplicité** : une seule commande pour tout démarrer ou arrêter

### 3.3. Fichier docker-compose.yml

Le fichier `docker-compose.yml` à la racine du projet orchestre les 4 services. 
Extrait du service application :

```yaml
app:
  build:
    context: .
    dockerfile: docker/php/Dockerfile
  container_name: locadjelo_app
  restart: unless-stopped
  working_dir: /var/www/html
  volumes:
    - ./src:/var/www/html
  networks:
    - locadjelo_network
  depends_on:
    - mysql
    - redis
  environment:
    - DB_HOST=mysql
    - DB_DATABASE=${DB_DATABASE}
    - DB_USERNAME=${DB_USERNAME}
    - DB_PASSWORD=${DB_PASSWORD}
```

**Points clés :**

- **build** : l'image est construite depuis notre Dockerfile personnalisé (PHP 7.4 
  + extensions Laravel)
- **restart: unless-stopped** : le container redémarre automatiquement en cas d'arrêt 
  inattendu
- **volumes** : le code source est monté en bind mount pour voir les modifications 
  immédiatement en développement
- **depends_on** : force l'ordre de démarrage (MySQL et Redis avant l'application)
- **environment** : les secrets sont injectés via un fichier `.env` non versionné

### 3.4. Variables d'environnement sécurisées

Les identifiants de base de données et autres secrets sont externalisés dans un 
fichier `.env` à la racine du projet, ignoré par Git. Un fichier `.env.example` 
sert de modèle :

```bash
DB_DATABASE=locadjelo
DB_USERNAME=locadjelo_user
DB_PASSWORD=locadjelo2026
DB_ROOT_PASSWORD=root_secure_2026
```

Cette séparation code/configuration respecte les principes des **12-factor apps** 
et permet de déployer le même code avec des configurations différentes (test, 
staging, production).

### 3.5. Démarrage et vérification de l'environnement

**Commandes essentielles :**

```bash
# Construction des images et démarrage
docker compose up -d --build

# Vérification de l'état des containers
docker compose ps

# Consultation des logs d'un service
docker compose logs app

# Arrêt sans suppression des volumes
docker compose down

# Arrêt avec suppression des volumes (réinitialisation complète)
docker compose down -v
```

**Résultat après démarrage :**
NAME               STATUS       PORTS
locadjelo_nginx    Up           0.0.0.0:8080->80/tcp
locadjelo_app      Up           9000/tcp
locadjelo_mysql    Up           0.0.0.0:3307->3306/tcp
locadjelo_redis    Up           6379/tcp

L'application est accessible sur `http://localhost:8080` après l'exécution des 
migrations Laravel :

```bash
docker compose exec app php artisan migrate --force
```

### 3.6. Critères de performance atteints

| Critère du référentiel | Statut | Preuve |
|---|---|---|
| L'environnement de tests est conforme au cahier des charges | Atteint | 4 services conformes à la production |
| Une version de test de l'application est produite | Atteint | LocaDjelo accessible sur localhost:8080 |
| Les tests sont effectués | Atteint | Migrations passées, application fonctionnelle |
| Les dysfonctionnements sont remontés aux développeurs | Atteint | Workflow Git + issues GitHub prévu |

---

## 4. Gérer le stockage des données (CP6)

### 4.1. Objectif

Le jury attend du candidat qu'il sache mettre en place un stockage persistant fiable, 
sécurisé et sauvegardé, conforme aux bonnes pratiques d'administration de bases 
de données.

### 4.2. Stratégie de stockage

Trois principes guident la gestion du stockage :

1. **Persistance** : les données doivent survivre aux redémarrages et recréations 
   de containers
2. **Sauvegarde** : des copies régulières protègent contre la perte de données
3. **Sécurité** : les droits d'accès respectent le principe du moindre privilège

### 4.3. Volumes Docker persistants

Deux volumes nommés Docker sont définis dans `docker-compose.yml` :

```yaml
volumes:
  mysql_data:
    driver: local
  redis_data:
    driver: local
```

**Différence entre bind mount et named volume :**

| Type | Utilisation | Avantages | Inconvénients |
|---|---|---|---|
| Bind mount | Code source en développement | Modifs visibles immédiatement | Dépend du système hôte |
| Named volume | Données persistantes (BDD) | Géré par Docker, portable | Moins accessible directement |

Les données MySQL utilisent un named volume car elles doivent être gérées par 
Docker et persister indépendamment du système hôte.

### 4.4. Test de persistance des données

Pour prouver que les volumes fonctionnent correctement, le test suivant a été 
réalisé :

**Étape 1 — Création d'un utilisateur témoin :**

```sql
INSERT INTO users (role_id, name, username, email, password, created_at, updated_at) 
VALUES (1, 'Test Persistance', 'test_persistance', 'test@locadjelo.fr', 'hash_test', NOW(), NOW());
```

**Étape 2 — Arrêt complet des containers :**

```bash
docker compose down
```

Cette commande supprime les 4 containers mais conserve les volumes.

**Étape 3 — Redémarrage des containers :**

```bash
docker compose up -d
```

**Étape 4 — Vérification de la présence de l'utilisateur :**

```sql
SELECT id, name, email FROM users WHERE email = 'test@locadjelo.fr';
```

**Résultat :**
+----+------------------+-------------------+
| id | name             | email             |
+----+------------------+-------------------+
|  1 | Test Persistance | test@locadjelo.fr |
+----+------------------+-------------------+

L'utilisateur est toujours présent après le redémarrage complet des containers, 
prouvant que **la persistance des volumes fonctionne correctement**.

### 4.5. Stratégie de sauvegarde automatisée

Un script Bash (`scripts/backup.sh`) automatise les sauvegardes MySQL avec les 
fonctionnalités suivantes :

- Export complet de la base via `mysqldump`
- Horodatage du nom de fichier (ex: `locadjelo_2026-04-16_09-30-23.sql`)
- Compression gzip (division de la taille par ~7)
- Rotation automatique : suppression des sauvegardes de plus de 7 jours
- Traçabilité : journal de toutes les opérations dans `backup.log`
- Gestion d'erreurs avec codes de sortie (0 = succès, 1 = erreur)

**Extrait significatif du script :**

```bash
# Création de la sauvegarde avec mysqldump
docker exec "$MYSQL_CONTAINER" sh -c \
    "mysqldump --no-tablespaces -u${DB_USER} -p${DB_PASSWORD} ${DB_NAME}" \
    > "$BACKUP_FILE"

# Compression pour économiser l'espace disque
gzip "$BACKUP_FILE"

# Rotation : suppression des sauvegardes anciennes
find "$BACKUP_DIR" -name "locadjelo_*.sql.gz" -mtime +${RETENTION_DAYS} -delete
```

### 4.6. Test de la sauvegarde et de la restauration

**Test complet de récupération de données :**

1. Création de la sauvegarde : `./scripts/backup.sh`
2. Suppression manuelle de l'utilisateur témoin dans MySQL
3. Vérification : `SELECT COUNT(*) FROM users` retourne 0
4. Restauration : `Get-Content backup.sql | docker compose exec -T mysql mysql ...`
5. Vérification : l'utilisateur est à nouveau présent

**Résultat du test de restauration :**

+----+------------------+-------------------+
| id | name             | email             |
+----+------------------+-------------------+
|  1 | Test Persistance | test@locadjelo.fr |
+----+------------------+-------------------+

Les données supprimées ont été correctement restaurées depuis la sauvegarde.

### 4.7. Planification de la sauvegarde (cron)

En production, le script est planifié via cron pour s'exécuter automatiquement 
chaque nuit :

```cron
0 2 * * * /opt/locadjelo/scripts/backup.sh >> /var/log/locadjelo-backup.log 2>&1
```

Cette ligne signifie : "tous les jours à 2h00 du matin, exécuter le script de 
sauvegarde et rediriger la sortie vers un fichier de log". Ce créneau est choisi 
car c'est une période de faible activité.

### 4.8. Gestion des droits d'accès MySQL

L'utilisateur MySQL applicatif `locadjelo_user` dispose uniquement des droits 
nécessaires sur la base `locadjelo` :

- Pas d'accès aux bases système (mysql, information_schema)
- Pas de privilèges administrateur (DROP DATABASE, CREATE USER, etc.)
- Utilisation séparée du compte root, réservé à l'administration

Cette séparation respecte le **principe du moindre privilège** : en cas de 
compromission de l'application, l'attaquant ne peut pas accéder à d'autres bases 
ou créer des utilisateurs.

### 4.9. Critères de performance atteints

| Critère du référentiel | Statut | Preuve |
|---|---|---|
| Les serveurs de données sont opérationnels | Atteint | Container MySQL Up, connexions applicatives OK |
| Les droits d'accès sont conformes au cahier des charges | Atteint | User applicatif limité à la BDD locadjelo |
| Les données sont sauvegardées | Atteint | Script automatisé + rotation + journalisation |

---

## 5. Gérer des containers (CP7)

### 5.1. Objectif

Le jury attend du candidat qu'il sache créer, configurer, connecter et maintenir 
à jour des containers Docker dans une architecture applicative cohérente.

### 5.2. Architecture en microservices

L'application LocaDjelo est décomposée en **4 microservices** conteneurisés, 
chacun ayant une responsabilité unique :

| Container | Responsabilité unique | Image de base |
|---|---|---|
| nginx | Routage HTTP et serveur web | nginx:alpine |
| app | Exécution du code PHP Laravel | php:7.4-fpm-alpine (personnalisée) |
| mysql | Persistance des données relationnelles | mysql:8.0 |
| redis | Cache mémoire et sessions | redis:alpine |

Ce découpage suit le **principe de responsabilité unique** : chaque container fait 
une seule chose, mais la fait bien. Cela facilite la maintenance, le scaling et le 
remplacement des composants.

### 5.3. Création d'une image personnalisée (Dockerfile)

Le service `app` utilise une image Docker construite spécifiquement pour LocaDjelo 
via un Dockerfile (`docker/php/Dockerfile`). Les choix techniques sont :

- **Image de base Alpine Linux** : 5 Mo au lieu de 80 Mo (Debian), réduction de la 
  surface d'attaque et rapidité de téléchargement
- **PHP 7.4-fpm** : version compatible avec le code existant de LocaDjelo
- **Extensions PHP compilées** : pdo_mysql, mbstring, zip, gd, bcmath, pcntl, exif 
  (toutes les extensions nécessaires à Laravel)
- **Utilisateur non-root** : un utilisateur `laravel` est créé pour ne jamais 
  exécuter l'application en root

**Extrait significatif du Dockerfile :**

```dockerfile
FROM php:7.4-fpm-alpine

# Installation des extensions PHP nécessaires à Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo pdo_mysql mbstring zip exif pcntl gd bcmath

# Création d'un utilisateur non-root pour la sécurité
RUN addgroup -g 1000 laravel && \
    adduser -u 1000 -G laravel -s /bin/sh -D laravel

# Copie du code avec les bonnes permissions
COPY --chown=laravel:laravel ./src /var/www/html

# Basculer sur l'utilisateur non-root
USER laravel

EXPOSE 9000
CMD ["php-fpm"]
```

### 5.4. Configuration du réseau inter-containers

Docker Compose crée automatiquement un **réseau bridge dédié** (`locadjelo_network`) 
sur lequel tous les containers sont connectés. Ce réseau permet :

- **La résolution DNS automatique** : chaque container est joignable par son nom 
  de service (ex: `mysql`, `redis`)
- **L'isolation du trafic** : le réseau est privé, inaccessible depuis l'extérieur
- **La communication sécurisée** : pas besoin d'exposer les ports MySQL/Redis sur 
  internet

**Exemple d'utilisation dans la configuration Nginx :**

```nginx
fastcgi_pass app:9000;
```

Cette ligne dit à Nginx de transmettre les requêtes PHP au container `app` sur le 
port 9000. Le nom `app` est résolu automatiquement par le DNS Docker.

### 5.5. Connexion au stockage distant

Deux types de connexions au stockage sont configurés :

**Volumes Docker (stockage persistant) :**

```yaml
volumes:
  - mysql_data:/var/lib/mysql    # MySQL
  - redis_data:/data              # Redis
```

**Bind mounts (code source, configuration) :**

```yaml
volumes:
  - ./src:/var/www/html                                  # Code Laravel
  - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
```

Cette configuration combine le meilleur des deux mondes : les données sont isolées 
et persistantes (volumes), tandis que le code est modifiable en temps réel 
(bind mount) pendant le développement.

### 5.6. Gestion des mises à jour des containers

**Procédure de mise à jour d'un service :**

1. Modifier le Dockerfile ou la version de l'image dans `docker-compose.yml`
2. Reconstruire l'image : `docker compose build <service>`
3. Relancer uniquement le service concerné : `docker compose up -d <service>`
4. Vérifier les logs : `docker compose logs -f <service>`

**Exemple concret rencontré dans ce projet :**

Lors de la première tentative de démarrage avec PHP 8.2, de nombreux warnings 
"Deprecated" sont apparus car le code Laravel n'était pas compatible. La procédure 
de mise à jour a été :

1. Modification du Dockerfile : `FROM php:8.2-fpm-alpine` → `FROM php:7.4-fpm-alpine`
2. Reconstruction : `docker compose down && docker compose up -d --build`
3. Vérification : l'application fonctionne sans warnings

Cette opération a été possible en quelques minutes grâce à l'Infrastructure as Code 
et aux containers. Sans cela, il aurait fallu désinstaller PHP 8.2 et installer 
PHP 7.4 sur le système hôte, avec tous les risques de conflits.

### 5.7. Commandes Docker utilisées dans le projet

| Commande | Rôle |
|---|---|
| `docker compose up -d --build` | Construire et démarrer tous les services |
| `docker compose down` | Arrêter et supprimer les containers (garder volumes) |
| `docker compose down -v` | Arrêter et supprimer containers + volumes (reset complet) |
| `docker compose ps` | Lister les containers du projet et leur état |
| `docker compose logs -f <service>` | Suivre les logs d'un service en temps réel |
| `docker compose exec <service> <cmd>` | Exécuter une commande dans un container |
| `docker compose exec app php artisan migrate` | Lancer les migrations Laravel |

### 5.8. Critères de performance atteints

| Critère du référentiel | Statut | Preuve |
|---|---|---|
| Les containers sont opérationnels | Atteint | 4 containers en état Up |
| Les containers sont connectés au réseau | Atteint | Réseau locadjelo_network + DNS interne |
| Les containers sont connectés au stockage distant | Atteint | Volumes mysql_data et redis_data attachés |
| Les containers sont mis à jour | Atteint | Procédure de rebuild documentée et testée |

---

## 6. Automatiser la mise en production (CP8) — Préparation

### 6.1. Positionnement dans le parcours

La compétence CP8 (Automatiser la mise en production d'une application avec une 
plateforme) sera traitée dans les rendus suivants du BC02 :

| Rendu | Date limite | Compétence abordée |
|---|---|---|
| Environnement de test (ce rendu) | 9 avril 2026 | CP5, CP6, CP7 partiel |
| Conteneurs Docker Compose | 16 avril 2026 | CP7 approfondi |
| Kubernetes (K3s) | 23 avril 2026 | CP7 + CP8 début |
| Pipeline CI/CD avancé | 30 avril 2026 | CP8 total |

### 6.2. Architecture cible pour la mise en production

L'environnement de test mis en place dans ce rendu constitue la **base technique** 
de la mise en production. Les rendus suivants ajouteront :

- **Orchestration Kubernetes** : passage de Docker Compose à K3s pour le scaling 
  et la haute disponibilité
- **Pipeline CI/CD GitHub Actions** : automatisation complète du cycle 
  build → test → push → deploy
- **Stratégies de déploiement** : blue/green, canary, rolling updates
- **Rollback automatique** : retour à la version précédente en cas d'échec

---

## 7. Problèmes rencontrés et recherches effectuées

### 7.1. Problème : Port 3306 déjà utilisé sur la machine hôte

**Contexte :** Lors du premier lancement de `docker compose up`, le container 
MySQL a échoué à démarrer.

**Erreur rencontrée :**

Error response from daemon: ports are not available:
exposing port TCP 0.0.0.0:3306 -> 127.0.0.1:0: listen tcp 0.0.0.0:3306: bind

**Recherche effectuée :** Lecture de la documentation Docker sur la gestion des 
ports. Le message indique qu'un autre processus utilise déjà le port 3306 sur la 
machine (MySQL local installé sur Windows).

**Solution trouvée :** Modification du mapping de ports dans `docker-compose.yml` :

```yaml
ports:
  - "3307:3306"   # Port externe différent, port interne inchangé
```

Le container MySQL conserve le port standard 3306 en interne (communication avec 
Laravel), mais est exposé sur le port 3307 côté hôte. Cette séparation permet de 
faire cohabiter plusieurs instances MySQL.

**Leçon apprise :** Dans Docker Compose, un mapping `"X:Y"` signifie "port X du 
host mappé sur le port Y du container". Il faut toujours vérifier que le port 
hôte est libre avant de lancer les containers.

### 7.2. Problème : Warnings PHP 8.2 sur du code Laravel ancien

**Contexte :** Après le démarrage réussi des containers, l'application générait 
de nombreux warnings "Deprecated" à chaque page.

**Erreur rencontrée :**

Deprecated: Return type of Illuminate\Container\Container::offsetExists($key)
should either be compatible with ArrayAccess::offsetExists(mixed $offset): bool

**Recherche effectuée :** Analyse des warnings. Tous provenaient de 
`vendor/laravel/framework/...`, indiquant que la version de Laravel installée 
utilisait des signatures de méthodes incompatibles avec PHP 8.2. La version de 
Laravel en place avait été écrite pour PHP 7.x.

**Solutions envisagées :**

1. Mettre à jour Laravel vers une version compatible PHP 8.2 (risque de casser 
   du code existant)
2. Basculer le container PHP vers la version 7.4 (compatible avec le code existant)

**Solution retenue :** Modification du Dockerfile pour utiliser `php:7.4-fpm-alpine`. 
Cette approche applique le principe DevOps "adapter l'environnement au code quand 
le code ne peut pas être modifié à court terme". Dans un second temps, une 
modernisation du code Laravel pourra être envisagée.

**Leçon apprise :** Les containers Docker permettent de figer une version d'exécution 
compatible avec du code existant, sans polluer le système hôte. C'est un avantage 
majeur de la conteneurisation : on peut faire cohabiter plusieurs versions de PHP 
sur la même machine sans conflit.

### 7.3. Problème : Erreur PowerShell lors de la restauration MySQL

**Contexte :** Lors du test de restauration, la commande avec redirection de 
fichier a échoué sous PowerShell.

**Erreur rencontrée :**
L'opérateur "<" est réservé à une utilisation future.

**Recherche effectuée :** Documentation PowerShell sur les redirections. Contrairement 
à Bash/CMD, PowerShell ne supporte pas l'opérateur `<` pour rediriger un fichier 
vers l'entrée standard d'une commande.

**Solution trouvée :** Utilisation de `Get-Content` avec un pipe :

```powershell
Get-Content scripts/backup.sql | docker compose exec -T mysql mysql -u... -p... locadjelo
```

**Leçon apprise :** Les scripts doivent être testés sur la cible d'exécution. Une 
commande qui fonctionne sous Bash peut échouer sous PowerShell. Pour des scripts 
vraiment portables, mieux vaut les écrire en Bash et les exécuter depuis WSL.

---

## 8. Synthèse et bilan du rendu

### 8.1. Récapitulatif des compétences couvertes

| Compétence | Intitulé | Statut | Preuve principale |
|---|---|---|---|
| CP5 | Préparer un environnement de test | Validée | Docker Compose 4 services fonctionnels |
| CP6 | Gérer le stockage des données | Validée | Volumes persistants + backup automatisé |
| CP7 | Gérer des containers | Validée (partiel) | Dockerfile + réseau + mises à jour |
| CP8 | Automatiser la mise en production | À venir | Rendus suivants du BC02 |

### 8.2. Ce qui fonctionne

- L'environnement de test Docker Compose démarre en une seule commande
- Les 4 containers communiquent entre eux via le réseau interne Docker
- L'application Laravel LocaDjelo est accessible sur localhost:8080
- Les volumes persistants garantissent la survie des données (test validé)
- Le script de sauvegarde automatisée fonctionne (backup + compression + rotation)
- La restauration depuis une sauvegarde a été testée avec succès
- La sécurité de base est en place (utilisateur non-root, moindre privilège MySQL)

### 8.3. Difficultés rencontrées et surmontées

- Conflit de ports avec MySQL déjà installé sur l'hôte
- Incompatibilité PHP 8.2 avec le code Laravel existant
- Différences de syntaxe entre PowerShell et Bash pour les redirections

### 8.4. Compétences acquises

- Création et configuration d'environnements Docker Compose multi-services
- Écriture de Dockerfiles personnalisés avec extensions PHP
- Gestion des volumes persistants Docker (named volumes vs bind mounts)
- Rédaction de scripts Bash de sauvegarde automatisée
- Utilisation de mysqldump pour l'export/restauration MySQL
- Configuration de réseaux Docker et DNS inter-containers
- Gestion des variables d'environnement sensibles
- Debug d'erreurs Docker (ports, permissions, compatibilité)

### 8.5. Prochaine étape

Le rendu suivant (16 avril) portera sur l'approfondissement de la conteneurisation 
avec une publication des images sur un registre (Docker Hub ou GitHub Container 
Registry), préparant ainsi le passage vers Kubernetes (23 avril) et la mise en 
place du pipeline CI/CD complet (30 avril).

---

## Annexes

- **Annexe A** : Code source complet — dépôt GitHub `locadjelo-app`
- **Annexe B** : Fichier `docker-compose.yml` complet — racine du projet
- **Annexe C** : Dockerfile personnalisé — `docker/php/Dockerfile`
- **Annexe D** : Script de sauvegarde — `scripts/backup.sh`
- **Annexe E** : Captures d'écran des tests de persistance et restauration