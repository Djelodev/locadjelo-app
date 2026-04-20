# BC02 — Rendu 3 : Kubernetes (K3s)

## Projet LocaDjelo — Titre professionnel ASD (RNCP 36061)

**Candidat** : Ange IRIE BI
**Date** : Avril 2026
**Formateur référent** : Max Fauquemberg

---

## 1. Introduction et contexte

### 1.1. Rappel du parcours

Ce document fait suite aux rendus précédents du BC02 :

- Rendu 1 : environnement de test Docker Compose (4 services fonctionnels)
- Rendu 2 : optimisation multi-stage build + publication Docker Hub

Ce troisième rendu porte sur le **déploiement de LocaDjelo sur un cluster 
Kubernetes (K3s)** sur le serveur Scaleway, avec scaling automatique.

### 1.2. Objectifs de ce rendu

- Installer et configurer un cluster Kubernetes (K3s) sur le serveur cloud
- Écrire les manifestes Kubernetes pour tous les services
- Déployer l'application conteneurisée depuis Docker Hub
- Démontrer le scaling horizontal des pods

### 1.3. Compétences couvertes

| Compétence | Intitulé | Aspect couvert |
|---|---|---|
| CP7 | Gérer des containers | Orchestration Kubernetes, pods, scaling |
| CP8 | Automatiser la mise en production | Déploiement K8s depuis registre Docker Hub |

---

## 2. Kubernetes : concepts et choix techniques

### 2.1. Pourquoi Kubernetes ?

Docker Compose orchestre plusieurs containers sur **une seule machine**. 
Kubernetes va plus loin en apportant :

- **Haute disponibilité** : si un pod tombe, Kubernetes en recrée un 
  automatiquement
- **Scaling horizontal** : augmenter ou diminuer le nombre de pods en une 
  commande
- **Déploiement déclaratif** : on décrit l'état souhaité dans des fichiers 
  YAML, Kubernetes s'occupe de l'atteindre
- **Gestion des secrets** : les mots de passe sont stockés de manière 
  sécurisée dans des objets Secret

### 2.2. Pourquoi K3s ?

K3s est une distribution légère de Kubernetes créée par Rancher, parfaite 
pour les environnements à ressources limitées :

| Critère | Kubernetes complet | K3s |
|---|---|---|
| RAM minimum | 4 Go | 512 Mo |
| Serveurs requis | 3 minimum | 1 suffisant |
| Installation | Complexe (kubeadm) | 1 commande (curl) |
| Fonctionnalités | Complètes | Identiques pour notre usage |
| Ingress Controller | À installer | Traefik inclus |

Notre VPS DEV1-S (2 Go RAM) est parfaitement adapté à K3s.

### 2.3. Installation de K3s

L'installation se fait en une seule commande sur le serveur Scaleway :

```bash
curl -sfL https://get.k3s.io | sh -
```

Vérification :

```bash
root@locadjelo-prod:~# kubectl get nodes
NAME             STATUS   ROLES           AGE    VERSION
locadjelo-prod   Ready    control-plane   2m9s   v1.34.6+k3s1
```

Le cluster est opérationnel avec un seul nœud jouant le rôle de 
control-plane (maître et travailleur).

---

## 3. Architecture Kubernetes de LocaDjelo

### 3.1. Vue d'ensemble des ressources

| Ressource | Type Kubernetes | Rôle |
|---|---|---|
| locadjelo | Namespace | Isolation de toutes les ressources du projet |
| mysql-secret | Secret | Stockage sécurisé des identifiants MySQL |
| mysql-pvc | PersistentVolumeClaim | Stockage persistant pour MySQL (5 Go) |
| mysql | Deployment + Service | Base de données (1 pod) |
| redis | Deployment + Service | Cache mémoire (1 pod) |
| nginx-config | ConfigMap | Configuration Nginx pour Laravel |
| locadjelo-app | Deployment + Service | Application Laravel + Nginx (2 pods) |
| locadjelo-ingress | Ingress | Point d'entrée HTTP depuis internet |

### 3.2. Organisation des manifestes

Les fichiers Kubernetes sont organisés dans le dossier k8s/ :

k8s/
├── namespace.yml    # Namespace locadjelo
├── mysql.yml        # Secret + PVC + Deployment + Service MySQL
├── redis.yml        # Deployment + Service Redis
├── app.yml          # ConfigMap + Deployment + Service Application
└── ingress.yml      # Ingress Traefik

### 3.3. Flux de déploiement

1. Les manifestes sont copiés sur le serveur via SCP (SSH)
2. kubectl apply déploie les ressources dans l'ordre : namespace, MySQL, 
   Redis, application, ingress
3. Kubernetes tire l'image djelodev/locadjelo-app:v1.0 depuis Docker Hub
4. Les pods démarrent et se connectent entre eux via les Services internes
5. L'Ingress Traefik route le trafic internet vers les pods applicatifs

---

## 4. Gestion des secrets

### 4.1. Principe

Les identifiants MySQL sont stockés dans un objet Kubernetes de type Secret, 
encodés en base64. Les pods y accèdent via des références (secretKeyRef) 
dans leurs variables d'environnement.

### 4.2. Avantages par rapport aux fichiers .env

| Aspect | Fichier .env (Docker Compose) | Secret Kubernetes |
|---|---|---|
| Stockage | Fichier texte sur le serveur | Objet Kubernetes chiffrable |
| Accès | Lisible par tous | Accès contrôlé par RBAC |
| Distribution | Copie manuelle | Automatique dans le cluster |
| Mise à jour | Redémarrage des containers | Rollout automatique possible |

---

## 5. Persistance des données

### 5.1. PersistentVolumeClaim

MySQL utilise un PersistentVolumeClaim (PVC) de 5 Go pour garantir la 
persistance des données :

```yaml
apiVersion: v1
kind: PersistentVolumeClaim
metadata:
  name: mysql-pvc
  namespace: locadjelo
spec:
  accessModes:
    - ReadWriteOnce
  resources:
    requests:
      storage: 5Gi
```

K3s provisionne automatiquement un PersistentVolume (PV) via son storage 
provider local-path. Les données MySQL survivent aux redémarrages et 
recréations de pods.

---

## 6. Pattern multi-containers : initContainer

### 6.1. Problème rencontré

Au premier déploiement, Nginx retournait une erreur 404. Le container 
Nginx n'avait pas accès au code source de Laravel car les deux containers 
du pod (app et nginx) ne partageaient pas de volume commun.

### 6.2. Solution : initContainer + emptyDir

Un **initContainer** est un container qui s'exécute avant les containers 
principaux. Il copie le code source depuis l'image Docker vers un volume 
partagé de type emptyDir :

```yaml
initContainers:
  - name: copy-code
    image: djelodev/locadjelo-app:v1.0
    command: ["sh", "-c", "cp -r /var/www/html/. /shared/"]
    volumeMounts:
      - name: app-code
        mountPath: /shared
```

Le volume emptyDir est ensuite monté dans les deux containers principaux 
(app et nginx) sur /var/www/html. Les deux containers accèdent au même 
code source.

### 6.3. Flux de démarrage d'un pod

1. initContainer copy-code démarre et copie le code dans le volume partagé
2. initContainer se termine
3. Container app (PHP-FPM) démarre et sert le code depuis le volume
4. Container nginx démarre et route les requêtes vers PHP-FPM

---

## 7. Scaling horizontal

### 7.1. Principe

Le scaling horizontal consiste à augmenter ou diminuer le nombre de pods 
pour s'adapter à la charge. Kubernetes gère automatiquement la distribution 
du trafic entre les pods via le Service.

### 7.2. Test de scaling réalisé

**Scaling de 2 à 3 pods :**

```bash
kubectl scale deployment locadjelo-app -n locadjelo --replicas=3
```

**Résultat vérifié :**

NAME                            READY   STATUS    AGE
locadjelo-app-79ff5967f-5phd9   2/2     Running   2m53s
locadjelo-app-79ff5967f-fd2sj   2/2     Running   30s
locadjelo-app-79ff5967f-qqx4j   2/2     Running   3m6s

Trois pods applicatifs tournent simultanément. L'application reste 
accessible pendant toute l'opération (zero downtime).

**Retour à 2 pods :**

```bash
kubectl scale deployment locadjelo-app -n locadjelo --replicas=2
```

Kubernetes supprime automatiquement un pod en douceur (graceful shutdown).

### 7.3. Gestion des limites de ressources

Chaque pod a des limites CPU et mémoire définies pour éviter qu'un pod 
ne monopolise les ressources du serveur :

| Container | Requests (garanti) | Limits (maximum) |
|---|---|---|
| app (PHP-FPM) | 128 Mi RAM, 100m CPU | 256 Mi RAM, 300m CPU |
| nginx | Par défaut | Par défaut |
| redis | 64 Mi RAM, 50m CPU | 128 Mi RAM, 100m CPU |

---

## 8. Commandes Kubernetes essentielles

| Commande | Rôle |
|---|---|
| kubectl apply -f fichier.yml | Déployer ou mettre à jour une ressource |
| kubectl get all -n locadjelo | Voir toutes les ressources du namespace |
| kubectl get pods -n locadjelo | Lister les pods et leur état |
| kubectl scale deployment ... --replicas=N | Changer le nombre de pods |
| kubectl logs pod/nom -n locadjelo -c app | Voir les logs d'un container |
| kubectl exec -n locadjelo deployment/... -- cmd | Exécuter une commande dans un pod |
| kubectl delete -f fichier.yml | Supprimer une ressource |

---

## 9. Problèmes rencontrés et recherches effectuées

### 9.1. Erreur 404 Nginx après le premier déploiement

**Contexte :** Après le déploiement initial, l'accès à http://163.172.161.220 
retournait "404 Not Found - nginx".

**Cause :** Le container Nginx n'avait pas accès au code Laravel. Dans 
Docker Compose, on utilisait un bind mount pour partager le code. Dans 
Kubernetes, les pods n'ont pas accès au système de fichiers de l'hôte de 
la même manière.

**Recherche effectuée :** Documentation Kubernetes sur les patterns 
multi-containers et les volumes partagés. Le pattern initContainer + 
emptyDir est la solution standard pour ce cas d'usage.

**Solution :** Ajout d'un initContainer qui copie le code source depuis 
l'image Docker vers un volume emptyDir partagé entre les containers app 
et nginx.

**Leçon apprise :** Kubernetes ne fonctionne pas comme Docker Compose. 
Les containers dans un pod partagent le réseau (localhost) mais pas le 
système de fichiers. Il faut explicitement configurer des volumes partagés.

### 9.2. Tables MySQL inexistantes

**Contexte :** Après le déploiement, l'application affichait l'erreur 
"Table locadjelo.properties doesn't exist".

**Cause :** Le pod MySQL était fraîchement créé avec une base de données 
vide (pas de tables).

**Solution :** Exécution des migrations Laravel dans le pod applicatif :

```bash
kubectl exec -n locadjelo deployment/locadjelo-app -c app -- php artisan migrate --force
```

**Leçon apprise :** Les migrations font partie du processus de déploiement. 
Dans un pipeline CI/CD, elles seront automatisées comme étape post-déploiement.

---

## 10. Synthèse et bilan

### 10.1. Récapitulatif des réalisations

| Réalisation | Détail | Preuve |
|---|---|---|
| Cluster K3s | Installation et configuration | kubectl get nodes : Ready |
| 5 manifestes | namespace, mysql, redis, app, ingress | Dossier k8s/ versionné |
| Déploiement complet | 4 pods opérationnels | kubectl get all : tous Running |
| Scaling horizontal | 2 → 3 → 2 pods | Test de scaling réussi, zero downtime |
| Accès internet | Application accessible | http://163.172.161.220 |
| Persistance | PVC 5 Go pour MySQL | Données survivent aux redémarrages |
| Secrets | Identifiants MySQL sécurisés | Secret Kubernetes en base64 |

### 10.2. Compétences acquises

- Installation et administration d'un cluster Kubernetes (K3s)
- Écriture de manifestes YAML (Deployment, Service, Secret, PVC, Ingress)
- Pattern multi-containers avec initContainer et volumes partagés
- Scaling horizontal et gestion des ressources (requests/limits)
- Déploiement depuis un registre Docker Hub
- Différences entre Docker Compose et Kubernetes
- Commandes kubectl essentielles

### 10.3. Prochaine étape

Le rendu du 30 avril portera sur la mise en place d'un **pipeline CI/CD** 
complet : push de code → build de l'image → tests → push Docker Hub → 
déploiement automatique sur Kubernetes. Ce sera l'aboutissement du BC02.

---

## Annexes

- **Annexe A** : Manifestes Kubernetes — dossier k8s/
- **Annexe B** : Image Docker Hub — https://hub.docker.com/r/djelodev/locadjelo-app
- **Annexe C** : Code source — https://github.com/Djelodev/locadjelo-app