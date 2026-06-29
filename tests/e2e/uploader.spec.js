const path = require('path');
const { expect, test } = require('@playwright/test');

test.describe.configure({ mode: 'serial' });

test.describe('phpUploader UI', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.waitForFunction(() => window.jQuery && window.fileManagerInstance);
  });

  test('renders the upload form and empty file list', async ({ page }) => {
    await expect(page).toHaveTitle(/PHP Uploader/);
    await expect(page.getByText('ファイルを登録')).toBeVisible();
    await expect(page.locator('#fileManagerContainer')).toContainText('ファイル一覧');
    await expect(page.locator('#fileManagerContainer')).toContainText('アップロードされたファイルはありません');
  });

  test('shows a client-side error when no file is selected', async ({ page }) => {
    await page.getByRole('button', { name: /アップロード/ }).click();

    await expect(page.locator('#errorContainer')).toBeVisible();
    await expect(page.locator('#errorContainer')).toContainText('ファイルを選択してください。');
  });

  test('uploads a file and supports searching and list view', async ({ page }) => {
    const fixturePath = path.join(__dirname, 'fixtures', 'sample-upload.pdf');

    await page.locator('#lefile').setInputFiles(fixturePath);
    await expect(page.locator('#fileInput')).toHaveValue('sample-upload.pdf');

    const uploadResponsePromise = page.waitForResponse((response) => (
      response.url().includes('/app/api/upload.php') &&
      response.request().method() === 'POST'
    ));

    await page.getByRole('button', { name: /アップロード/ }).click();

    const uploadResponse = await uploadResponsePromise;
    expect(uploadResponse.ok()).toBeTruthy();

    const payload = await uploadResponse.json();
    expect(payload.status).toBe('success');

    await expect(page.locator('.file-card-v2__filename')).toContainText('sample-upload.pdf');

    await page.locator('#fileSearchInput').fill('sample');
    await expect(page.locator('.file-card-v2__filename')).toContainText('sample-upload.pdf');

    await page.locator('.file-view-toggle__btn[data-view="list"]').click();
    await expect(page.locator('.file-list-item__filename')).toContainText('sample-upload.pdf');

    await page.locator('#fileSearchInput').fill('missing-file');
    await expect(page.locator('#fileManagerContainer')).toContainText('検索結果が見つかりません');
  });
});
