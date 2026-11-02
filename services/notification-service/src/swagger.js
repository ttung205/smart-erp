import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import swaggerUi from 'swagger-ui-express';
import { parse } from 'yaml';

const specPath = fileURLToPath(new URL('../openapi.yaml', import.meta.url));

/** Đọc openapi.yaml một lần khi khởi động; file sai cú pháp làm dịch vụ không lên, không để tài liệu lỗi âm thầm. */
export const openApiDocument = parse(readFileSync(specPath, 'utf8'));

/** Swagger UI tại /swagger, cùng giao diện với các service .NET. Chỉ bật khi docsEnabled. */
export function mountSwagger(app, { docsEnabled }) {
  if (!docsEnabled) return;
  app.get('/swagger/openapi.json', (req, res) => res.json(openApiDocument));
  app.use('/swagger', swaggerUi.serve, swaggerUi.setup(openApiDocument, {
    customSiteTitle: 'Smart ERP - Notification Service API',
  }));
}

/** Local và development mới bật tài liệu, giống quy tắc chỉ mở ở local của các service khác. */
export function isDocsEnabled(appEnv = process.env.APP_ENV ?? '') {
  return ['local', 'development'].includes(appEnv.toLowerCase());
}
