# BC02 — Rendu 2 : Conteneurisation avancée

## Projet LocaDjelo — Titre professionnel ASD (RNCP 36061)

**Candidat** : Ange IRIE BI
**Date** : Avril 2026
**Formateur référent** : Max Fauquemberg

---

## 1. Introduction et contexte

### 1.1. Rappel du parcours

Ce document fait suite au premier rendu du BC02 (environnement de test Docker 
Compose). L'environnement de test étant fonctionnel, ce second rendu porte sur 
l'**optimisation des images Docker** et leur **publication sur un registre** 
(Docker Hub), préparant le déploiement sur le serveur de production Scaleway.

### 1.2. Objectifs de ce rendu

- Optimiser le Dockerfile avec un **multi-stage build** pour réduire la taille 
  et la surface d'attaque de l'image
- **Publier les images** sur Docker Hub pour les rendre accessibles depuis 
  n'importe quel serveur
- Approfondir la compétence CP7 (Gérer des containers)

### 1.3. Compétence couverte

| Compétence | Intitulé | Aspect couvert |
|---|---|---|
| CP7 | Gérer des containers | Multi-stage build, registre, versioning d'images |

---

## 2. Optimisation du Dockerfile : multi-stage build

### 2.1. Problème de l'approche single-stage

L'image Docker initiale pesait **318 Mo**. Elle contenait :

- Les outils de compilation (gcc, make, headers de développement)
- Composer (gestionnaire de dépendances PHP)
- Git, curl et d'autres utilitaires de build
- Les librairies de développement (libpng-dev, freetype-dev, etc.)

Ces outils sont nécessaires pour **construire** l'image mais inutiles pour 
**exécuter** l'application. Les conserver dans l'image finale pose deux 
problèmes :

- **Taille excessive** : 318 Mo au lieu du nécessaire
- **Surface d'attaque** : chaque outil présent est une porte d'entrée 
  potentielle pour un attaquant (gcc, git, etc.)

### 2.2. Principe du multi-stage build

Le multi-stage build sépare la construction de l'exécution en deux étapes 
dans un seul Dockerfile :

- **Stage 1 (builder)** : installe tous les outils, compile les extensions 
  PHP, télécharge les dépendances via Composer. Ce stage est temporaire.
- **Stage 2 (final)** : repart d'une image Alpine propre, copie uniquement 
  les fichiers nécessaires depuis le stage builder (extensions compilées + 
  code source avec dépendances).

L'instruction clé est `COPY --from=builder` qui permet de copier des fichiers 
d'un stage à l'autre sans embarquer les outils de compilation.

### 2.3. Extrait significatif du Dockerfile

```dockerfile
# Stage 1 : compilation et installation
FROM php:7.4-fpm-alpine AS builder

RUN apk add --no-cache libpng-dev freetype-dev $PHPIZE_DEPS
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY ./src /var/www/html
RUN composer install --no-dev --optimize-autoloader

# Stage 2 : image finale légère
FROM php:7.4-fpm-alpine AS final

# Uniquement les librairies d'exécution (pas les headers -dev)
RUN apk add --no-cache libpng libjpeg-turbo freetype libzip

# Copie des extensions compilées depuis le builder
COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copie du code source avec dépendances
COPY --from=builder --chown=laravel:laravel /var/www/html /var/www/html

USER laravel
CMD ["php-fpm"]
```

### 2.4. Résultat obtenu

| Métrique | Avant (single-stage) | Après (multi-stage) | Gain |
|---|---|---|---|
| Taille de l'image | 318 Mo | 229 Mo | -28% |
| Outils de compilation | Présents | Absents | Surface d'attaque réduite |
| Composer | Présent | Absent | Non nécessaire à l'exécution |
| Git | Présent | Absent | Non nécessaire à l'exécution |

L'image finale ne contient que le strict nécessaire pour exécuter Laravel : 
PHP-FPM, les extensions compilées, les librairies d'exécution, et le code 
source avec ses dépendances.

### 2.5. Bonnes pratiques appliquées

- **Image de base Alpine** : distribution minimale (~5 Mo)
- **Utilisateur non-root** : l'application tourne sous l'utilisateur `laravel`
- **Séparation build/run** : aucun outil de développement en production
- **Labels** : métadonnées (maintainer, description, version) pour traçabilité
- **EXPOSE explicite** : documentation du port utilisé (9000)

---

## 3. Publication sur Docker Hub

### 3.1. Pourquoi un registre d'images ?

Un registre Docker est un serveur centralisé qui stocke et distribue des 
images Docker. Sans registre, il faudrait construire l'image sur chaque 
serveur où on veut la déployer. Avec un registre :

- On **construit une seule fois** (en local ou en CI/CD)
- On **pousse** l'image sur le registre
- N'importe quel serveur peut **tirer** l'image et la lancer

C'est le même principe que GitHub pour le code : un dépôt centralisé 
accessible de partout.

### 3.2. Choix de Docker Hub

| Critère | Docker Hub | Justification |
|---|---|---|
| Popularité | N°1 mondial | Standard de l'industrie |
| Coût | Gratuit (images publiques) | Adapté au projet |
| Intégration | Compatible Docker natif | Pas de configuration supplémentaire |
| Fiabilité | 99.9% uptime | Images toujours accessibles |

### 3.3. Processus de publication

La publication suit trois étapes :

**Étape 1 — Connexion au registre :**

```bash
docker login
```

**Étape 2 — Tagging de l'image avec la convention Docker Hub :**

```bash
# Tag "latest" : version la plus récente
docker tag locadjelo-app-app:latest djelodev/locadjelo-app:latest

# Tag versionné : version fixe pour la traçabilité
docker tag locadjelo-app-app:latest djelodev/locadjelo-app:v1.0
```

La convention de nommage est `utilisateur/nom_image:version`. Le double 
tagging (latest + version) est une bonne pratique :

- `latest` permet de toujours obtenir la dernière version
- `v1.0` permet de figer une version précise (rollback possible)

**Étape 3 — Push vers Docker Hub :**

```bash
docker push djelodev/locadjelo-app:latest
docker push djelodev/locadjelo-app:v1.0
```

### 3.4. Résultat

L'image est accessible publiquement sur :
**https://hub.docker.com/r/djelodev/locadjelo-app**

N'importe quel serveur peut maintenant télécharger et lancer l'application :

```bash
docker pull djelodev/locadjelo-app:v1.0
```

### 3.5. Workflow de déploiement rendu possible

Grâce au registre, le workflow de déploiement en production devient :

1. Le développeur pousse du code sur GitHub
2. L'image Docker est construite et publiée sur Docker Hub
3. Le serveur Scaleway tire la nouvelle image
4. Le container est recréé avec la nouvelle version

Ce workflow sera automatisé dans le rendu CI/CD (30 avril).

---

## 4. Problèmes rencontrés et recherches effectuées

### 4.1. Erreur 400 lors du premier push Docker Hub

**Contexte :** Le premier `docker push` vers Docker Hub a échoué avec une 
erreur HTTP 400 (Bad Request).

**Erreur rencontrée :**

unknown: failed commit on ref "layer-sha256:ca7dd9ec..."
unexpected status from PUT request: 400 Bad request


**Recherche effectuée :** Consultation des forums Docker. L'erreur 400 lors 
d'un push est généralement causée par un problème réseau temporaire ou un 
timeout lors de l'upload d'une couche volumineuse.

**Solution trouvée :** Relancer la commande `docker push`. Docker détecte 
automatiquement les couches déjà envoyées ("Layer already exists") et 
n'envoie que celles qui manquent. Le second push a réussi intégralement.

**Leçon apprise :** Les opérations réseau peuvent échouer. Docker est conçu 
pour reprendre les uploads partiels, ce qui rend le processus résilient. 
Il suffit de relancer la commande.

---

## 5. Synthèse et bilan

### 5.1. Récapitulatif des réalisations

| Réalisation | Détail | Preuve |
|---|---|---|
| Multi-stage build | Dockerfile optimisé en 2 stages | Image réduite de 318 Mo à 229 Mo |
| Publication Docker Hub | 2 tags publiés (latest + v1.0) | hub.docker.com/r/djelodev/locadjelo-app |
| Versioning d'images | Convention latest + version numérotée | Tags latest et v1.0 disponibles |
| Sécurité renforcée | Pas d'outils de compilation en production | Image finale sans gcc, git, composer |

### 5.2. Compétences acquises

- Écriture de Dockerfiles multi-stage pour optimiser les images
- Publication et versioning d'images sur un registre Docker
- Compréhension du workflow build → tag → push → pull
- Gestion des erreurs réseau lors des push Docker
- Bonnes pratiques de sécurité des images (Alpine, non-root, minimal)

### 5.3. Prochaine étape

Le rendu du 23 avril portera sur **Kubernetes (K3s)** : déployer 
l'application LocaDjelo conteneurisée sur un cluster Kubernetes, avec 
scaling automatique, en utilisant les images publiées sur Docker Hub.

---

## Annexes

- **Annexe A** : Dockerfile multi-stage complet — `docker/php/Dockerfile`
- **Annexe B** : Image publiée — https://hub.docker.com/r/djelodev/locadjelo-app
- **Annexe C** : Code source — https://github.com/Djelodev/locadjelo-app

