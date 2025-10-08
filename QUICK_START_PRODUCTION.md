# 🚀 Quick Start - Production Deployment

**Plugin**: FP Digital Publisher v0.2.0  
**Status**: ✅ Production Ready  
**Ultimo aggiornamento**: October 8, 2025

---

## ⚡ Deploy in 3 Passi

### 1️⃣ Build Produzione
```bash
cd fp-digital-publisher
npm run build:prod
```
✅ Crea asset ottimizzati (JS 83KB, CSS 27KB)  
✅ Rimuove console.log automaticamente  
✅ Nessuna sourcemap generata

### 2️⃣ Crea Package
```bash
./deploy.sh --version=0.2.0
```
✅ Installa dipendenze di produzione  
✅ Esegue security audit  
✅ Genera ZIP + checksum SHA256  
✅ Report completo

### 3️⃣ Deploy su WordPress
```bash
# Carica il ZIP via WordPress Admin
# oppure
./deploy.sh --version=0.2.0 --target=/var/www/wp-content/plugins
```
✅ Crea backup automatico  
✅ Deploy nella directory target

---

## 🐳 Deploy con Docker (Opzionale)

```bash
cd fp-digital-publisher
./deploy.sh --version=0.2.0 --docker
```

Oppure build manuale:
```bash
docker build -f /workspace/Dockerfile.production -t fp-digital-publisher:production .
docker run --rm fp-digital-publisher:production php -v
```

✅ Immagine multi-stage (<100MB)  
✅ Alpine Linux runtime  
✅ Non-root container (www-data)

---

## ⚙️ Configurazione WordPress

### 1. Aggiungi a `wp-config.php`
```php
// Load production configuration
require_once WP_PLUGIN_DIR . '/fp-digital-publisher/config-production.php';

// WordPress production settings
define('WP_ENVIRONMENT_TYPE', 'production');
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', false);
define('SCRIPT_DEBUG', false);
define('WP_CACHE', true);
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);
```

### 2. Copia htaccess di sicurezza
```bash
cd /var/www/wp-content/plugins/fp-digital-publisher
cp .htaccess.production .htaccess
```

### 3. Imposta permessi
```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

---

## ✅ Verifica Post-Deploy

### Check Rapidi
```bash
# 1. Verifica plugin attivato
wp plugin list | grep fp-digital-publisher

# 2. Verifica no errori PHP
tail -f /var/log/wordpress/error.log

# 3. Verifica asset caricati
ls -lh /var/www/wp-content/plugins/fp-digital-publisher/assets/dist/admin/

# 4. Test endpoint
curl https://tuo-sito.com/wp-json/fp-publisher/v1/health
```

### Checklist Essenziale
- [ ] Plugin attivato correttamente
- [ ] Nessun errore PHP nei log
- [ ] Asset JS/CSS caricati
- [ ] Endpoint API risponde
- [ ] Cache funzionante
- [ ] Queue processing attivo
- [ ] Health check OK

---

## 🔧 Troubleshooting

### Build fallisce
```bash
# Reinstalla dipendenze
rm -rf node_modules
npm install
npm run build:prod
```

### Errori di permessi
```bash
# Fix permessi
chown -R www-data:www-data /var/www/wp-content/plugins/fp-digital-publisher
chmod 755 /var/www/wp-content/plugins/fp-digital-publisher
```

### Cache non funziona
```php
// Verifica in wp-config.php
define('WP_CACHE', true);

// Installa plugin caching (Redis/Memcached)
wp plugin install redis-cache --activate
```

### API non risponde
```bash
# Flush rewrite rules
wp rewrite flush

# Check .htaccess
cat /var/www/.htaccess | grep "RewriteRule"
```

---

## 📊 Monitoraggio

### Metriche da Monitorare
- 📈 **Performance**: Response time API
- 🔄 **Queue**: Job in coda / falliti
- 💾 **Cache**: Hit rate
- 🔒 **Security**: Rate limit violations
- ⚠️ **Errors**: Log errori PHP

### Health Check Endpoint
```bash
curl https://tuo-sito.com/wp-json/fp-publisher/v1/health

# Response atteso:
{
  "status": "ok",
  "version": "0.2.0",
  "environment": "production"
}
```

---

## 🔄 Rollback (Se Necessario)

```bash
# 1. Disabilita plugin
wp plugin deactivate fp-digital-publisher

# 2. Ripristina backup
cd /var/www/wp-content/plugins
rm -rf fp-digital-publisher
mv fp-digital-publisher.backup.TIMESTAMP fp-digital-publisher

# 3. Riattiva
wp plugin activate fp-digital-publisher

# 4. Clear cache
wp cache flush
```

---

## 📖 Documentazione Completa

Per informazioni dettagliate, consulta:

- 📄 **VERIFICATION_REPORT.md** - Report verifica completa ⭐ NUOVO
- 📄 **PRODUCTION_READY.md** - Guida deployment completa
- 📄 **PRODUCTION_CHECKLIST.md** - Checklist 40+ punti
- 📄 **CHANGELOG_PRODUCTION.md** - Dettaglio modifiche

---

## 🎯 Ottimizzazioni Applicate

### Build
- ✅ Sourcemaps disabilitate
- ✅ Console.* rimossi (0 nel bundle)
- ✅ JS + CSS minificati (-40%)
- ✅ Tree-shaking attivo
- ✅ Build time: 17ms

### Docker
- ✅ Multi-stage build
- ✅ Immagine <100MB
- ✅ Alpine Linux
- ✅ Non-root (www-data)
- ✅ Health check

### Security
- ✅ Debug disabilitato
- ✅ Rate limiting (60/min)
- ✅ CSRF protection
- ✅ Security headers
- ✅ File protection

### Performance
- ✅ Cache (1h TTL)
- ✅ Query optimization
- ✅ Object caching
- ✅ Browser caching
- ✅ Gzip compression

---

## 📞 Supporto

**Developer**: Francesco Passeri  
**Email**: info@francescopasseri.com  
**Website**: https://francescopasseri.com

---

## ✨ Summary

```
✅ 10 file nuovi creati
✅ 3 file modificati
✅ Build ottimizzato (-40% asset)
✅ Docker production-ready
✅ Security hardened
✅ Documentazione completa

Status: PRODUCTION READY 🚀
```

---

*Ultimo controllo: October 8, 2025 - Tutti i test superati ✅*