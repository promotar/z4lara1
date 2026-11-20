<?php

// Ephemeral local browser QA only. Never distributed or exposed as an HTTP route.
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;

if (PHP_SAPI !== 'cli' || getenv('CERTIFICATES_LOCAL_QA') !== '1') {
    throw new RuntimeException('Local QA authorization required.');
}
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$state = '/tmp/certificates-local-browser-qa.json';
$sessionRecord = '/tmp/certificates-local-browser-qa.session';
$session = app('session')->driver();
if (($argv[1] ?? '') === '--clear') {
    if (is_file($sessionRecord)) {
        $session->getHandler()->destroy(trim(file_get_contents($sessionRecord)));
        unlink($sessionRecord);
    }
    if (is_file($state)) {
        unlink($state);
    }
    echo "Local QA session invalidated.\n";
    exit;
}
if (is_file($sessionRecord) || is_file($state)) {
    throw new RuntimeException('Clear the previous local QA session first.');
}
$admin = User::role(['admin', 'super-admin'])->orderBy('id')->firstOrFail();
$session->start();
$session->put(Auth::guard('web')->getName(), $admin->getAuthIdentifier());
$session->save();
$name = config('session.cookie');
$encrypter = app('encrypter');
$value = $encrypter->encrypt(CookieValuePrefix::create($name, $encrypter->getKey()).$session->getId(), false);
file_put_contents($state, json_encode(['cookies' => [[
    'name' => $name, 'value' => $value, 'domain' => 'localhost', 'path' => '/',
    'expires' => -1, 'httpOnly' => true, 'secure' => false, 'sameSite' => 'Lax',
]], 'origins' => []], JSON_THROW_ON_ERROR));
file_put_contents($sessionRecord, $session->getId());
chmod($state, 0600);
chmod($sessionRecord, 0600);
echo "Ephemeral local QA state created; no user credentials changed.\n";
