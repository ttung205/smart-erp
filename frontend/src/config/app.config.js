export const appConfig = {
  appName: 'Smart ERP',
  architecture: 'Microservices & Database-per-Service',
  eventRelay: 'Transactional Outbox & RabbitMQ',
  services: {
    identity: {
      name: 'Identity Service',
      port: 8001,
      healthUrl: '/api/v1/auth/health'
    },
    catalog: {
      name: 'Catalog Service',
      port: 8002,
      healthUrl: '/api/v1/catalog/health'
    }
  }
};
