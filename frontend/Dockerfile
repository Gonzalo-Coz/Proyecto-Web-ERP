# Frontend Vue/Vite para Railway — build estático servido por nginx.
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
# VITE_API_URL queda relativo (/api/v1); nginx lo proxya al backend (ver plantilla).
RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
# nginx:alpine procesa las plantillas de /etc/nginx/templates con envsubst al arrancar
# (sustituye solo ${PORT} y ${BACKEND_URL}; deja intactas las variables propias de nginx).
COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
