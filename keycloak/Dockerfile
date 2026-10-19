# syntax=docker/dockerfile:1
#
# Build: docker build -t securities-manager-keycloak ./keycloak
#
# Production image (compose.prod.yml). The realm import has no users and takes
# APP_URL and KEYCLOAK_CLIENT_SECRET from the environment; it only runs while
# the realm does not exist yet.

FROM quay.io/keycloak/keycloak:26.0 AS builder

ENV KC_DB=postgres
ENV KC_HEALTH_ENABLED=true
ENV KC_METRICS_ENABLED=true

RUN /opt/keycloak/bin/kc.sh build

FROM quay.io/keycloak/keycloak:26.0

COPY --from=builder /opt/keycloak/ /opt/keycloak/
COPY realm-securities-manager.prod.json /opt/keycloak/data/import/realm-securities-manager.json

ENV KC_HTTP_ENABLED=true
ENV KC_HEALTH_ENABLED=true
ENV KC_METRICS_ENABLED=true

EXPOSE 8080

# Health endpoints are on the management port 9000 since Keycloak 25.
HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 \
    CMD exec 3<>/dev/tcp/127.0.0.1/9000 && echo -e 'GET /health/ready HTTP/1.1\r\nHost: localhost\r\nConnection: close\r\n\r\n' >&3 && cat <&3 | grep -q '200 OK'

ENTRYPOINT ["/opt/keycloak/bin/kc.sh"]
CMD ["start", "--optimized", "--import-realm"]
