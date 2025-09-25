# Linee guida per l'iniezione delle dipendenze

*Autore: Francesco Passeri – [francescopasseri.com](https://francescopasseri.com) – [info@francescopasseri.com](mailto:info@francescopasseri.com)*

Versione minima: **1.0.0**  
Ultimo aggiornamento documentazione: **1.0.1**

Il plugin utilizza un contenitore compatibile con PSR-11 per gestire le dipendenze condivise e ridurre l'uso di variabili globali. Il contenitore vive in `includes/class-tts-service-container.php` ed è accessibile tramite l'helper `tsap_service_container()` definito nel file principale del plugin.

## Accesso al contenitore

```php
$container = tsap_service_container();
```

L'helper restituisce sempre la stessa istanza di `TTS_Service_Container`, così da poter registrare e risolvere servizi in modo centralizzato.

## Registrazione dei servizi core

La funzione `tsap_register_default_services()` definisce i servizi fondamentali del plugin (scheduler, integration hub, logger, rate limiter e security audit). Viene invocata durante il bootstrap del plugin, dopo il caricamento delle classi necessarie.

Ogni servizio viene registrato come "shared" (singleton) mediante closure che ricevono il contenitore come primo argomento quando lo richiedono:

```php
$container->set('scheduler', function ( TTS_Service_Container $c ) {
    return new TTS_Scheduler( $c->get( 'integration_hub' ) );
});
```

Per registrare factory non condivise è possibile usare il flag `$shared = false` oppure il metodo `factory()` del contenitore.

## Estensione del contenitore

Dopo la registrazione dei servizi di base vengono emessi due hook:

- `tsap_container_registered` dopo la definizione dei servizi core, utile per aggiungere nuovi servizi.
- `tsap_container_bootstrapped` dopo la risoluzione delle istanze principali, utile per reagire al bootstrap completo.

Gli add-on possono sfruttare questi hook per arricchire il contenitore senza modificare il core del plugin.

## Risoluzione e utilizzo delle dipendenze

Per ottenere un servizio registrato è sufficiente chiamare `get()` sul contenitore:

```php
$scheduler = $container->get( 'scheduler' );
```

Le classi dovrebbero ricevere le dipendenze tramite costruttore (o metodi dedicati) invece di ricorrere a variabili globali o `require` dinamici. Il `TTS_Rate_Limiter`, ad esempio, accetta un logger esterno e ricade sul logger statico solo se non viene fornito.

## Buone pratiche

- Evitare l'utilizzo diretto di `$GLOBALS` per condividere istanze. Registrare invece i servizi nel contenitore.
- Centralizzare la creazione dei servizi complessi (scheduler, integrazioni, pagine admin) dentro il contenitore per migliorare la testabilità.
- Usare il contenitore anche nei test: è possibile istanziare un nuovo `TTS_Service_Container` e registrare manualmente le dipendenze necessarie.

Seguire queste indicazioni consente di mantenere il codice modulare, favorire il riuso delle componenti e facilitare l'estensione del plugin da parte di terzi.

## Riferimenti
- [docs/architecture/target-operating-model.md](target-operating-model.md)
- [README.md](../../README.md)
- [CHANGELOG.md](../../CHANGELOG.md)
