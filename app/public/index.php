<?php

use App\Repository\TranslationRepository;
use App\Repository\LanguageRepository;

require_once dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

error_reporting(E_ALL);
ini_set('display_errors', '0');

$client = \Symfony\Component\Cache\Adapter\RedisAdapter::createConnection(
    sprintf(
        "redis://%s:%s",
        filter_var($_ENV['REDIS_HOST'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS),
        filter_var($_ENV['REDIS_PORT'] ?? '6379', FILTER_VALIDATE_INT) ?: 6379
    )
);

$cacheAdapter = new \Symfony\Component\Cache\Adapter\RedisAdapter($client);

if (isset($_GET['health'])) {
    echo json_encode(['status' => 'ok']);
    exit;
}

$translation = null;
$languages = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $languageId = filter_input(INPUT_POST, 'language', FILTER_VALIDATE_INT);
    $phrase = filter_input(INPUT_POST, 'phrase', FILTER_SANITIZE_SPECIAL_CHARS);

    if ($languageId === false || $languageId === null || $phrase === false || $phrase === null || $phrase === '') {
        http_response_code(400);
        $translation = 'Invalid input. Select a language and enter a phrase.';
    } else {
        $translationCache = new \App\Cache\TranslationCache($cacheAdapter, new TranslationRepository());
        $translation = $translationCache->findForLanguage($languageId, $phrase) ?: 'Translation not found...';
    }

} else {

    $languageRepository = new LanguageRepository();
    $languages = $languageRepository->findAll();
}

?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Translate This</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.2/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-Zenh87qX5JnK2Jl0vWa8Ck2rdkQ2Bzep5IDxbcnCeuOxjzrPF/et3URy9Bv1WTRi" crossorigin="anonymous">

    <style>
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }

        .b-example-divider {
            height: 3rem;
            background-color: rgba(0, 0, 0, .1);
            border: solid rgba(0, 0, 0, .15);
            border-width: 1px 0;
            box-shadow: inset 0 .5em 1.5em rgba(0, 0, 0, .1), inset 0 .125em .5em rgba(0, 0, 0, .15);
        }

        .b-example-vr {
            flex-shrink: 0;
            width: 1.5rem;
            height: 100vh;
        }

        .bi {
            vertical-align: -.125em;
            fill: currentColor;
        }

        .nav-scroller {
            position: relative;
            z-index: 2;
            height: 2.75rem;
            overflow-y: hidden;
        }

        .nav-scroller .nav {
            display: flex;
            flex-wrap: nowrap;
            padding-bottom: 1rem;
            margin-top: -1px;
            overflow-x: auto;
            text-align: center;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
        }

        @media (min-width: 992px) {
            .rounded-lg-3 {
                border-radius: .3rem;
            }
        }
    </style>

</head>
<body>
<div class="bg-dark text-secondary px-4 py-5 text-center" style="height: 100vh">
    <div class="py-5">
        <h1 class="display-5 fw-bold text-white">Translate This</h1>
        <div class="col-lg-6 mx-auto">
            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                <p class="fs-5 mb-4"><?php echo htmlspecialchars($translation ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <a href="/">Translate another</a>
            <?php else: ?>

                <p class="fs-5 mb-4">Select a language and enter your word(s).</p>

                <form method="post">
                    <div class="row g-3">
                        <div class="col">
                            <select name="language" class="form-select" aria-label="Default select example">
                                <option selected>Select a language</option>
                                <?php foreach ($languages ?? [] as $language): ?>
                                    <option value="<?php echo (int) $language->getId(); ?>">
                                        <?php echo htmlspecialchars($language->getName(), ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <input name="phrase" type="text" class="form-control" placeholder="Phrase" aria-label="Phrase"
                                   maxlength="255">
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-info" type="submit">Translate</button>
                        </div>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
