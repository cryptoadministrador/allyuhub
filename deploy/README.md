# Despliegue del piloto — droplet `elearnium` (allyu.cuysoft.io)

AllyuHub convive aquí con el Moodle beta de `e-learnium.edu.ec`
(`/root/moodle-docker`, que NO se toca). El nginx del host es la fachada
TLS de ambos; la app corre en contenedores (PHP 8.4 + PostgreSQL 16).

## Instalar / actualizar

```bash
# Primera vez
cd /root && git clone https://github.com/cryptoadministrador/allyuhub.git
cd allyuhub && bash deploy/install.sh

# Actualizaciones posteriores (tras mergear a main)
cd /root/allyuhub && git pull && bash deploy/install.sh
```

`install.sh` es idempotente: swap, contraseña de BD (deploy/.env.db, fuera
de git), contenedores, composer+npm, migraciones, claves LTI, vhost y
certificado. La cookie de sesión sale None+Secure+Partitioned — el punto
entero del piloto (docs/lti-moodle.md §3).

## Por qué allyu.cuysoft.io y no un subdominio de e-learnium.edu.ec

SameSite trata a `allyu.e-learnium.edu.ec` y `e-learnium.edu.ec` como el
MISMO sitio: el iframe no cruzaría sitios y el piloto no probaría nada.
Con cuysoft.io el cruce es real, como lo será con el Moodle de cualquier
otro colegio.
