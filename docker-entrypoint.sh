#!/bin/bash
set -e

# Désactiver les MPM en conflit avant de démarrer Apache
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Démarrer Apache
exec apache2-foreground
