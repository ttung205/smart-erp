export const appConfig = {
  appName: 'Smart ERP',
  version: 'v2.0',
  architecture: 'Microservices & Database-per-Service',
  eventRelay: 'Transactional Outbox & RabbitMQ',
  company: {
    name: 'Smart Enterprise Solution JSC',
    taxId: '0102030405',
    hotline: '1900-6868'
  },
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
