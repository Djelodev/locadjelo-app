# BC02 — Rendu 4 : Pipeline CI/CD

## Projet LocaDjelo — Titre professionnel ASD (RNCP 36061)

**Candidat** : Ange IRIE BI
**Date** : Avril-Mai 2026
**Formateur référent** : Max Fauquemberg

---

## 1. Introduction et contexte

### 1.1. Rappel du parcours BC02

Ce document est le dernier rendu du BC02 et fait suite aux rendus précédents :

- Rendu 1 : environnement de test Docker Compose (4 services)
- Rendu 2 : optimisation multi-stage build + publication Docker Hub
- Rendu 3 : déploiement Kubernetes (K3s) avec scaling

Ce quatrième rendu porte sur la mise en place d'un **pipeline CI/CD complet** 
avec GitHub Actions, automatisant le cycle build, test et déploiement de 
l'application LocaDjelo.

### 1.2. Objectifs

- Automatiser la construction de l'image Docker à chaque push
- Automatiser les tests de l'application
- Automatiser le déploiement sur Kubernetes en production
- Sécuriser le pipeline avec la gestion des secrets GitHub

### 1.3. Compétence couverte

| Compétence | Intitulé | Aspect couvert |
|---|---|---|
| CP8 | Automatiser la mise en production avec une plateforme | Pipeline CI/CD complet (build + test + deploy) |

---

## 2. Qu'est-ce que le CI/CD ?

### 2.1. Définition

Le CI/CD est une pratique DevOps qui automatise le cycle de vie du code :

- **CI (Continuous Integration)** : à chaque modification du code poussée sur 
  le dépôt, le pipeline construit automatiquement l'application et exécute les 
  tests. Si un test échoue, l'équipe est alertée immédiatement.

- **CD (Continuous Deployment)** : si tous les tests passent, l'application est 
  automatiquement déployée en production, sans intervention humaine.

### 2.2. Pourquoi le CI/CD ?

Sans CI/CD, le déploiement est manuel : le développeur construit l'image, la 
pousse sur Docker Hub, se connecte en SSH au serveur, et met à jour Kubernetes 
à la main. C'est lent, source d'erreurs, et non reproductible.

Avec CI/CD, le développeur pousse son code sur GitHub et tout se fait 
automatiquement en quelques minutes. C'est plus rapide, fiable et traçable.

### 2.3. Choix de GitHub Actions

| Critère | GitHub Actions | Justification |
|---|---|---|
| Intégration | Natif GitHub | Pas d'outil externe à configurer |
| Coût | Gratuit (repos publics) | 2000 min/mois inclus |
| Simplicité | Fichier YAML dans le repo | Versionné avec le code |
| Écosystème | Marketplace d'actions | Actions prêtes à l'emploi |

---

## 3. Architecture du pipeline

### 3.1. Workflow global

Le pipeline se déclenche automatiquement à chaque push et suit un flux 
différent selon la branche :

**Push sur develop** : Build de l'image Docker → Tests → Stop (pas de 
déploiement)

**Push/merge sur main** : Build → Tests → Push image Docker Hub → Déploiement 
automatique sur Kubernetes (production)

### 3.2. Les 3 jobs du pipeline

| Job | Durée | Rôle | Condition |
|---|---|---|---|
| Build Docker Image | ~1m 30s | Construire l'image depuis le Dockerfile | Toujours |
| Tests applicatifs | ~1m 40s | Vérifier la structure et le build | Après build réussi |
| Déploiement production | ~38s | Mettre à jour Kubernetes via SSH | Seulement sur main |

### 3.3. Flux détaillé

Le pipeline complet (sur main) exécute ces étapes :

1. GitHub détecte le push et lance le pipeline
2. **Job Build** : checkout du code, connexion Docker Hub, build de l'image 
   multi-stage, push de l'image avec le tag du commit SHA
3. **Job Test** : lance un MySQL de test, vérifie la structure du projet 
   (Dockerfile, docker-compose, k8s), vérifie que l'image se construit
4. **Job Deploy** : se connecte en SSH au serveur Scaleway, met à jour le 
   deployment Kubernetes avec la nouvelle image, attend le rollout, vérifie 
   que les pods sont Running

Durée totale : environ 4 minutes du push au déploiement.

---

## 4. Le fichier pipeline : ci-cd.yml

### 4.1. Déclencheurs

```yaml
on:
  push:
    branches:
      - develop    # CI uniquement
      - main       # CI + CD
  pull_request:
    branches:
      - main       # CI sur les pull requests
```

Le pipeline se déclenche sur les push vers develop et main, et sur les pull 
requests vers main. Cela garantit que tout code qui arrive sur main a été 
testé.

### 4.2. Job Build — Construction de l'image

```yaml
- name: Build de l'image Docker
  run: |
    docker build -f docker/php/Dockerfile -t $DOCKER_IMAGE:$DOCKER_TAG .
    docker tag $DOCKER_IMAGE:$DOCKER_TAG $DOCKER_IMAGE:latest
```

L'image est taguée avec le SHA du commit Git. Chaque commit produit une 
image unique et traçable. On peut toujours revenir à une version précédente.

### 4.3. Job Test — Vérifications

Le job de test lance un container MySQL temporaire, attend qu'il soit prêt, 
puis vérifie que tous les fichiers essentiels du projet existent et que 
l'image Docker se construit sans erreur.

### 4.4. Job Deploy — Déploiement Kubernetes

```yaml
- name: Déploiement sur Scaleway via SSH
  uses: appleboy/ssh-action@v1
  with:
    host: ${{ secrets.SERVER_HOST }}
    script: |
      kubectl set image deployment/locadjelo-app \
        app=djelodev/locadjelo-app:$TAG -n locadjelo
      kubectl rollout status deployment/locadjelo-app \
        -n locadjelo --timeout=120s
```

La commande kubectl set image met à jour l'image du deployment Kubernetes. 
Kubernetes effectue alors un rolling update : il crée de nouveaux pods avec 
la nouvelle image, vérifie qu'ils fonctionnent, puis supprime les anciens. 
L'application reste accessible pendant toute l'opération.

---

## 5. Gestion des secrets

### 5.1. Principe

Les informations sensibles (mots de passe Docker Hub, clé SSH, adresse IP 
du serveur) ne sont jamais écrites dans le code. Elles sont stockées dans 
les **GitHub Secrets**, un coffre-fort chiffré intégré à GitHub.

### 5.2. Secrets configurés

| Secret | Rôle | Utilisé par |
|---|---|---|
| DOCKER_USERNAME | Nom d'utilisateur Docker Hub | Job Build |
| DOCKER_PASSWORD | Mot de passe Docker Hub | Job Build |
| SERVER_HOST | Adresse IP du serveur Scaleway | Job Deploy |
| SERVER_USER | Utilisateur SSH (root) | Job Deploy |
| SERVER_SSH_KEY | Clé privée SSH | Job Deploy |

### 5.3. Utilisation dans le pipeline

Les secrets sont injectés via la syntaxe ${{ secrets.NOM_DU_SECRET }}. 
GitHub les masque automatiquement dans les logs du pipeline — même si un 
script affiche la valeur, GitHub remplace par ***.

---

## 6. Résultats obtenus

### 6.1. Pipeline sur develop (CI uniquement)

Le pipeline a été testé sur la branche develop :

- Build Docker Image : ✅ Succès (1m 24s)
- Tests applicatifs : ✅ Succès (1m 41s)
- Déploiement production : ⊘ Skipped (normal, pas sur main)

Le déploiement est correctement conditionné : il ne s'exécute que sur main.

### 6.2. Pipeline sur main (CI + CD complet)

Après merge de develop dans main, le pipeline complet s'est exécuté :

- Build Docker Image : ✅ Succès (1m 30s)
- Tests applicatifs : ✅ Succès (1m 40s)
- Déploiement production : ✅ Succès (38s)

Durée totale : 4 minutes 3 secondes. L'application a été automatiquement 
mise à jour sur le serveur Kubernetes en production.

---

## 7. Stratégie de déploiement et rollback

### 7.1. Rolling Update

Kubernetes effectue un rolling update lors du déploiement : il lance de 
nouveaux pods avec la nouvelle version avant de supprimer les anciens. 
L'application reste accessible pendant toute l'opération (zero downtime).

### 7.2. Rollback

En cas de problème avec une nouvelle version, Kubernetes permet un retour 
en arrière immédiat :

```bash
kubectl rollout undo deployment/locadjelo-app -n locadjelo
```

Cette commande revient à la version précédente en quelques secondes.

### 7.3. Historique des déploiements

Chaque image Docker est taguée avec le SHA du commit Git. Cela permet de 
tracer exactement quelle version du code est déployée et de revenir à 
n'importe quelle version précédente.

---

## 8. Problèmes rencontrés et recherches effectuées

### 8.1. Compte GitHub bloqué (billing)

**Contexte :** Le premier lancement du pipeline a échoué immédiatement avec 
l'erreur "your account is locked due to a billing issue".

**Cause :** GitHub Actions nécessite un moyen de paiement vérifié, même pour 
les dépôts publics gratuits. Une vérification de carte bancaire (1€) était 
nécessaire.

**Solution :** Ajout d'un moyen de paiement sur le compte GitHub. Le 1€ de 
vérification est remboursé.

**Leçon apprise :** Toujours vérifier les prérequis d'un service (facturation, 
quotas, permissions) avant de l'utiliser dans un pipeline de production.

---

## 9. Synthèse et bilan BC02 complet

### 9.1. Récapitulatif du BC02

| Rendu | Compétence | Réalisation | Statut |
|---|---|---|---|
| R1 — Env de test | CP5, CP6 | Docker Compose 4 services, volumes, backup | ✅ |
| R2 — Conteneurs | CP7 | Multi-stage build, Docker Hub | ✅ |
| R3 — Kubernetes | CP7, CP8 | K3s, 5 manifestes, scaling | ✅ |
| R4 — CI/CD | CP8 | GitHub Actions, 3 jobs, deploy auto | ✅ |

### 9.2. Le workflow complet de bout en bout

1. Le développeur modifie le code et push sur develop
2. GitHub Actions build l'image et lance les tests (CI)
3. Si tout passe, le développeur merge develop dans main
4. GitHub Actions rebuild, reteste, push l'image sur Docker Hub
5. GitHub Actions se connecte en SSH au serveur Scaleway
6. Kubernetes met à jour les pods avec la nouvelle image (rolling update)
7. L'application est mise à jour en production sans coupure

Ce workflow est entièrement automatisé : du push au déploiement en 4 minutes.

### 9.3. Compétences acquises sur l'ensemble du BC02

- Conteneurisation avec Docker et Docker Compose
- Écriture de Dockerfiles optimisés (multi-stage build)
- Publication et versioning d'images sur Docker Hub
- Orchestration Kubernetes (K3s) avec scaling horizontal
- Écriture de pipelines CI/CD avec GitHub Actions
- Gestion sécurisée des secrets (GitHub Secrets)
- Stratégies de déploiement (rolling update, rollback)
- Sauvegarde et restauration de bases de données

---

## Annexes

- **Annexe A** : Pipeline CI/CD — `.github/workflows/ci-cd.yml`
- **Annexe B** : Captures d'écran des pipelines (develop + main)
- **Annexe C** : Code source — https://github.com/Djelodev/locadjelo-app
- **Annexe D** : Image Docker — https://hub.docker.com/r/djelodev/locadjelo-app
