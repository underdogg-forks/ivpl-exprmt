const { test, expect } = require('@playwright/test');

test('application root responds', async ({ request, baseURL }) => {
  const response = await request.get(baseURL || '/');
  expect(response.status()).toBeLessThan(500);
});
