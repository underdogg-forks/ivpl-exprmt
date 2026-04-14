const { test, expect } = require('@playwright/test');

test('integrations page responds', async ({ request, baseURL }) => {
  const response = await request.get((baseURL || 'http://127.0.0.1:8080') + '/integrations/index');
  expect(response.status()).toBeLessThan(400);
});
