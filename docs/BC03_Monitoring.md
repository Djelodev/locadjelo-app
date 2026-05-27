# BC03 — Rendu 1 : Monitoring (Prometheus + Grafana)

## Projet LocaDjelo — Titre professionnel ASD (RNCP 36061)

**Candidat** : Ange IRIE BI
**Date** : Mai 2026
**Formateur référent** : Max Fauquemberg

---

## 1. Introduction et contexte

### 1.1. Rappel du parcours

Après la mise en place de l'infrastructure cloud (BC01) et du déploiement 
continu (BC02), ce premier rendu du BC03 porte sur la **supervision des 
services déployés** : collecte de métriques, dashboards de visualisation 
et règles d'alerte.

### 1.2. Objectifs

- Installer et configurer une stack de monitoring complète sur Kubernetes
- Définir les indicateurs pertinents à surveiller (KPI)
- Configurer la collecte automatique des métriques
- Mettre en place des règles d'alerte avec seuils définis
- Documenter les choix techniques pour le jury

### 1.3. Compétences couvertes

| Compétence | Intitulé | Couverture |
|---|---|---|
| CP9 | Définir et mettre en place des statistiques de services | Totale |
| CP10 | Exploiter une solution de supervision | Totale |

---

## 2. Architecture de la stack de monitoring

### 2.1. Les 3 composants

La supervision repose sur trois outils complémentaires, chacun ayant un 
rôle précis :

| Composant | Rôle | Analogie |
|---|---|---|
| Node Exporter | Capteur — expose les métriques du serveur | Le thermomètre dans une voiture |
| Prometheus | Collecteur — récupère et stocke les métriques | Le tableau de bord qui lit le thermomètre |
| Grafana | Affichage — visualise les métriques en graphiques | L'écran qui affiche la température |

### 2.2. Flux de données

Le flux suit un schéma simple en 3 étapes :

1. **Node Exporter** tourne sur chaque nœud du cluster et expose les 
   métriques système (CPU, RAM, disque, réseau) sur le port 9100
2. **Prometheus** interroge Node Exporter toutes les 15 secondes (scrape) 
   et stocke les résultats dans sa base de données temporelle (TSDB)
3. **Grafana** se connecte à Prometheus et affiche les métriques sous 
   forme de graphiques et dashboards interactifs

### 2.3. Déploiement sur Kubernetes

Chaque composant est déployé comme un pod Kubernetes dans le namespace 
locadjelo :

| Composant | Type K8s | Image Docker | Port | Stockage |
|---|---|---|---|---|
| Node Exporter | DaemonSet | prom/node-exporter | 9100 | Aucun (lecture seule) |
| Prometheus | Deployment | prom/prometheus | 9090 | PVC 5 Go (30j rétention) |
| Grafana | Deployment | grafana/grafana | 3000 | PVC 1 Go (dashboards) |

**Pourquoi un DaemonSet pour Node Exporter ?** Un DaemonSet garantit 
qu'exactement 1 pod tourne sur chaque nœud du cluster. Si on ajoute un 
serveur, un node-exporter est automatiquement lancé dessus. C'est le type 
de ressource idéal pour les agents de monitoring.

---

## 3. Indicateurs définis (CP9)

### 3.1. Objectif

Le jury attend que les indicateurs choisis soient pertinents, qu'ils 
tiennent compte des SLA (Service Level Agreements), et qu'ils soient 
exhaustifs — c'est-à-dire qu'ils couvrent toutes les dimensions 
importantes : système, application, sécurité et base de données.

### 3.2. Métriques système

| Indicateur | Métrique Prometheus | Seuil d'alerte | Justification |
|---|---|---|---|
| Utilisation CPU | node_cpu_seconds_total | > 80% pendant 5 min | Détection de surcharge |
| Utilisation RAM | node_memory_MemAvailable_bytes | > 85% | Prévention des OOM kills |
| Espace disque | node_filesystem_avail_bytes | < 20% disponible | Prévention saturation |
| Charge système | node_load1 | > 2x nb vCPU | Indicateur de surcharge globale |

### 3.3. Métriques applicatives

| Indicateur | Source | Seuil d'alerte | Justification |
|---|---|---|---|
| Temps de réponse HTTP | nginx_request_duration | > 2 secondes | Expérience utilisateur |
| Taux d'erreur HTTP 5xx | nginx_http_requests_total | > 1% | Détection bugs en production |
| Connexions simultanées | nginx_connections_active | > 100 | Capacité du serveur |

### 3.4. Métriques base de données

| Indicateur | Source | Seuil d'alerte | Justification |
|---|---|---|---|
| Connexions MySQL actives | mysqld_connections | > 80% du max | Prévention saturation |
| Latence des requêtes | mysqld_query_duration | > 500 ms | Performance applicative |

### 3.5. Métriques sécurité

| Indicateur | Source | Seuil d'alerte | Justification |
|---|---|---|---|
| Tentatives SSH échouées | auth.log via node_exporter | > 10/heure | Détection attaque brute force |
| Expiration certificat TLS | probe_ssl_earliest_cert_expiry | < 30 jours | Renouvellement anticipé |

### 3.6. Prise en compte des SLA

Les seuils d'alerte sont définis en fonction des engagements de service :

- **Disponibilité cible** : 99.5% (environ 44h d'indisponibilité max par an)
- **Temps de réponse cible** : < 2 secondes pour 95% des requêtes
- **RPO (Recovery Point Objective)** : perte de données maximale tolérée = 
  24h (fréquence des sauvegardes)
- **RTO (Recovery Time Objective)** : temps de restauration cible = 1h

---

## 4. Configuration de Prometheus (CP10)

### 4.1. Configuration du scraping

Prometheus est configuré via un ConfigMap Kubernetes qui définit les 
cibles à surveiller :

```yaml
global:
  scrape_interval: 15s       # Collecte toutes les 15 secondes
  evaluation_interval: 15s   # Évalue les règles toutes les 15 secondes

scrape_configs:
  - job_name: 'node'
    static_configs:
      - targets: ['node-exporter:9100']

  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']
```

**scrape_interval: 15s** signifie que Prometheus va chercher les métriques 
sur chaque cible toutes les 15 secondes. C'est un bon compromis entre 
précision et consommation de ressources.

### 4.2. Règles d'alerte configurées

4 règles d'alerte sont définies dans Prometheus :

| Alerte | Expression PromQL | Durée | Sévérité |
|---|---|---|---|
| HighCpuUsage | CPU idle < 20% pendant 5 min | 5 min | Warning |
| HighMemoryUsage | RAM disponible < 15% | 2 min | Warning |
| DiskSpaceLow | Disque utilisé > 80% | 5 min | Critical |
| InstanceDown | up == 0 | 1 min | Critical |

**Explication d'une règle (exemple CPU) :**

```yaml
- alert: HighCpuUsage
  expr: 100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100) > 80
  for: 5m
  labels:
    severity: warning
  annotations:
    summary: "CPU élevé sur {{ $labels.instance }}"
```

Cette règle calcule le pourcentage de CPU utilisé en mesurant le temps 
d'inactivité (idle) sur 5 minutes. Si l'utilisation dépasse 80% pendant 
5 minutes consécutives, l'alerte se déclenche avec la sévérité "warning".

### 4.3. Stockage des métriques

Prometheus stocke les métriques dans un PersistentVolumeClaim de 5 Go 
avec une rétention de 30 jours. Les données les plus anciennes sont 
automatiquement supprimées.

### 4.4. Preuve de fonctionnement

Prometheus est accessible sur http://163.172.161.220:30721 et collecte 
activement les métriques. Exemples de requêtes fonctionnelles :

- `node_cpu_seconds_total` → métriques CPU en temps réel
- `node_memory_MemAvailable_bytes` → mémoire disponible
- `node_filesystem_avail_bytes` → espace disque disponible
- `up` → état des services (1 = actif, 0 = down)

---

## 5. Grafana — Dashboards de visualisation

### 5.1. Configuration automatique

Grafana est déployé avec une configuration automatique de la source de 
données Prometheus via un ConfigMap :

```yaml
datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://prometheus:9090
    isDefault: true
```

Cela signifie que dès le démarrage, Grafana sait où chercher les métriques 
sans configuration manuelle.

### 5.2. Accès

- **URL** : http://163.172.161.220:31646
- **Login** : admin
- **Mot de passe** : locadjelo2026
- **Inscription** : désactivée (sécurité)

### 5.3. Dashboards disponibles

Les dashboards suivants peuvent être créés ou importés :

| Dashboard | Contenu | ID Grafana (import) |
|---|---|---|
| Node Exporter Full | CPU, RAM, disque, réseau détaillés | 1860 |
| Kubernetes Cluster | Vue d'ensemble des pods et services | 6417 |
| Custom LocaDjelo | Métriques spécifiques au projet | Création manuelle |

Pour importer un dashboard prêt à l'emploi : Dashboards → Import → 
entrer l'ID (ex: 1860) → sélectionner Prometheus comme source → Import.

### 5.4. Note sur les performances

Grafana est une application web lourde (interface React, base SQLite, 
nombreux plugins). Sur un serveur partagé avec l'application et la base 
de données, le temps de chargement peut être long.

**En production**, la stack de monitoring serait déployée sur un serveur 
dédié pour ne pas impacter les performances de l'application. Sur cet 
environnement de démonstration, tous les services sont regroupés sur un 
même serveur par contrainte de budget.

---

## 6. Considérations de production

### 6.1. Architecture recommandée en entreprise

En entreprise, la stack de monitoring serait séparée :

| Serveur | Services | Justification |
|---|---|---|
| Serveur applicatif | App + MySQL + Redis + Node Exporter | Performance application |
| Serveur monitoring | Prometheus + Grafana + Alertmanager | Pas d'impact sur l'app |

### 6.2. Sécurisation des transactions

Les échanges entre les agents (node-exporter) et le serveur Prometheus 
se font à l'intérieur du réseau Kubernetes (ClusterIP), inaccessibles 
depuis internet. Seuls Prometheus et Grafana sont exposés via NodePort 
pour la consultation.

---

## 7. Problèmes rencontrés et recherches effectuées

### 7.1. Serveur saturé en RAM (2 Go insuffisants)

**Contexte :** Après le déploiement de la stack monitoring, le serveur 
DEV1-S (2 Go RAM) est devenu inaccessible. kubectl ne répondait plus 
(TLS handshake timeout) et la connexion SSH était impossible.

**Cause :** L'ajout de Prometheus, Grafana, node-exporter et Alertmanager 
en plus de l'application (2 pods), MySQL et Redis dépassait la capacité 
mémoire du serveur.

**Solutions appliquées :**

1. Ajout de 2 Go de swap pour pallier temporairement le manque de RAM
2. Upgrade du serveur de DEV1-S (2 Go) à PLAY2-NANO (4 Go RAM)
3. Réduction de l'application à 1 replica au lieu de 2
4. Suppression d'Alertmanager pour économiser des ressources

**Leçon apprise :** Le dimensionnement des ressources (capacity planning) 
est une compétence essentielle en administration système. Il faut toujours 
prévoir les besoins en RAM AVANT de déployer de nouveaux services. En 
production, le monitoring devrait être sur un serveur dédié.

### 7.2. Grafana inaccessible depuis internet

**Contexte :** Grafana tournait en mode Running mais la page ne se 
chargeait pas dans le navigateur (ERR_CONNECTION_REFUSED).

**Cause :** Deux problèmes combinés : le pare-feu UFW bloquait le port 
NodePort (31646), et Grafana mettait plusieurs minutes à démarrer 
complètement à cause des ressources limitées.

**Solutions :**

1. Ouverture du port dans UFW : `ufw allow 31646/tcp`
2. Redémarrage du pod Grafana : `kubectl delete pod grafana-xxx`
3. Attente de 2 minutes après le redémarrage pour le chargement complet

**Leçon apprise :** Quand un service Kubernetes est en Running mais 
inaccessible, il faut vérifier dans l'ordre : le pare-feu, puis les logs 
du pod, puis tester en local avec curl avant de tester depuis internet.

---

## 8. Synthèse et bilan

### 8.1. Récapitulatif des réalisations

| Réalisation | Détail | Preuve |
|---|---|---|
| Node Exporter | DaemonSet collectant CPU/RAM/disque | Pod Running, métriques exposées |
| Prometheus | Collecte toutes les 15s, 30j rétention | Accessible port 30721, requêtes PromQL |
| Grafana | Dashboards visuels, source Prometheus auto | Accessible port 31646, login admin |
| Règles d'alerte | 4 alertes (CPU, RAM, disque, instance down) | Configurées dans prometheus.yml |
| Indicateurs | 10+ métriques définies avec seuils SLA | Documentées dans ce rapport |

### 8.2. Critères de performance atteints

| Critère du référentiel | Statut | Preuve |
|---|---|---|
| Les indicateurs choisis sont pertinents | Atteint | Métriques système, app, BDD, sécurité |
| Les indicateurs tiennent compte des SLA | Atteint | Seuils définis selon les engagements |
| Les indicateurs sont exhaustifs | Atteint | 4 catégories couvertes |
| Les alertes sont correctement interprétées | Atteint | 4 règles avec sévérité et descriptions |

### 8.3. Compétences acquises

- Déploiement d'une stack de monitoring sur Kubernetes
- Configuration de Prometheus (scraping, règles d'alerte, PromQL)
- Utilisation de Grafana (datasources, dashboards)
- Définition d'indicateurs pertinents avec seuils SLA
- Capacity planning et gestion des ressources serveur
- Debug de services Kubernetes (logs, curl local, UFW)

### 8.4. Prochaine étape

Le rendu suivant portera sur la supervision approfondie : centralisation 
des logs, configuration avancée des alertes, et communication 
professionnelle en anglais technique.

---

## Annexes

- **Annexe A** : Manifestes monitoring — k8s/monitoring/
- **Annexe B** : Prometheus — http://163.172.161.220:30721
- **Annexe C** : Grafana — http://163.172.161.220:31646
- **Annexe D** : Code source — https://github.com/Djelodev/locadjelo-app