import assert from 'node:assert/strict';
import { once } from 'node:events';
import { createServer } from 'node:http';
import { after, before, describe, it } from 'node:test';
import { createApp } from '../src/app.js';
import { isDocsEnabled, openApiDocument } from '../src/swagger.js';

async function startServer(docsEnabled) {
  const server = createServer(createApp({ database: {}, repository: {}, jwtSecret: 'swagger-test', docsEnabled }));
  server.listen(0);
  await once(server, 'listening');
  return { server, baseUrl: `http://127.0.0.1:${server.address().port}` };
}

describe('Swagger UI', () => {
  describe('khi bật (local)', () => {
    let server;
    let baseUrl;

    before(async () => {
      ({ server, baseUrl } = await startServer(true));
    });
    after(() => server.close());

    it('phục vụ Swagger UI tại /swagger mà không cần JWT', async () => {
      const response = await fetch(`${baseUrl}/swagger/`);

      assert.equal(response.status, 200);
      assert.match(await response.text(), /swagger-ui/i);
    });

    it('phục vụ spec OpenAPI tại /swagger/openapi.json', async () => {
      const response = await fetch(`${baseUrl}/swagger/openapi.json`);
      const spec = await response.json();

      assert.equal(response.status, 200);
      assert.equal(spec.info.title, 'Smart ERP - Notification Service API');
      assert.deepEqual(Object.keys(spec.components.securitySchemes).sort(), ['bearerAuth', 'internalServiceKey']);
    });
  });

  describe('khi tắt (production)', () => {
    let server;
    let baseUrl;

    before(async () => {
      ({ server, baseUrl } = await startServer(false));
    });
    after(() => server.close());

    it('không phục vụ /swagger', async () => {
      const response = await fetch(`${baseUrl}/swagger/`);

      assert.equal(response.status, 404);
    });

    it('vẫn giữ nguyên API nghiệp vụ, vẫn yêu cầu JWT', async () => {
      const response = await fetch(`${baseUrl}/api/v1/notifications`);

      assert.equal(response.status, 401);
    });
  });

  it('spec khai báo đầy đủ mọi route thông báo và email', () => {
    const paths = Object.keys(openApiDocument.paths).sort();

    assert.deepEqual(paths, [
      '/api/v1/notifications',
      '/api/v1/notifications/email/logs',
      '/api/v1/notifications/email/logs/{id}',
      '/api/v1/notifications/read-all',
      '/api/v1/notifications/unread-count',
      '/api/v1/notifications/{id}/read',
      '/health',
    ]);
  });

  it('route public không khai báo bảo mật, route nghiệp vụ dùng JWT', () => {
    assert.deepEqual(openApiDocument.paths['/health'].get.security, []);
    assert.equal(openApiDocument.paths['/api/v1/notifications'].get.security, undefined);
    assert.deepEqual(openApiDocument.paths['/api/v1/notifications/email/logs'].get.security, [
      { bearerAuth: [] },
      { internalServiceKey: [] },
    ]);
  });

  it('isDocsEnabled chỉ bật ở local và development', () => {
    assert.equal(isDocsEnabled('local'), true);
    assert.equal(isDocsEnabled('Development'), true);
    assert.equal(isDocsEnabled('production'), false);
    assert.equal(isDocsEnabled(''), false);
  });
});
