# Deployment

- No se usa Laravel Cloud. Se despliega en un servidor on-premise con Docker.
- Flujo:
  1. En el PC, construir la imagen de producción (`Dockerfile.prod`) con su versión: `IMAGE=ghcr.io/luisolavubo/api-auth:<versión> docker compose -f docker-compose.prod.yml build app`.
  2. Publicarla en GitHub Packages: `docker push ghcr.io/luisolavubo/api-auth:<versión>`.
  3. En el servidor, con `IMAGE` en su `.env`: `docker compose -f docker-compose.prod.yml pull` y `docker compose -f docker-compose.prod.yml up -d --no-build`.
- El detalle (archivos del servidor, llaves de Passport, proxy inverso) está en la sección "Despliegue" del README.
