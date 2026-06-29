const fs = require('fs');
const path = require('path');

const rootDirectory = path.resolve(__dirname, '..', '..');
const runtimeDirectory = path.join(rootDirectory, '.test-runtime');
const runtimeConfigDirectory = path.join(runtimeDirectory, 'config');

fs.rmSync(runtimeDirectory, { recursive: true, force: true });
fs.mkdirSync(runtimeConfigDirectory, { recursive: true });
fs.mkdirSync(path.join(runtimeDirectory, 'db'), { recursive: true });
fs.mkdirSync(path.join(runtimeDirectory, 'data'), { recursive: true });
fs.mkdirSync(path.join(runtimeDirectory, 'logs'), { recursive: true });

const templatePath = path.join(rootDirectory, 'config', 'config.php.example');
const configPath = path.join(runtimeConfigDirectory, 'config.php');

let config = fs.readFileSync(templatePath, 'utf8');
config = config
  .replace("'CHANGE_THIS_MASTER_KEY'", "'test-master-key'")
  .replace("'CHANGE_THIS_ENCRYPTION_KEY'", "'test-encryption-key'")
  .replace("'CHANGE_THIS_sessionSalt'", "'test-session-salt'")
  .replace("__DIR__ . '/../composer.json'", "__DIR__ . '/../../composer.json'");

fs.writeFileSync(configPath, config);

console.log(`Prepared isolated test runtime at ${runtimeDirectory}`);
