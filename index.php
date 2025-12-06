<?php
declare(strict_types=1);

// data ディレクトリを基準に全てのスニペットを読み込む
function load_all_snippets(string $dataDir, array &$errors): array
{
    if (!is_dir($dataDir)) {
        return [];
    }

    $snippets = [];
    $files = glob($dataDir . '/*.json');

    foreach ($files as $file) {
        $json = @file_get_contents($file);
        if ($json === false) {
            $errors[] = sprintf('ファイル「%s」の読み込みに失敗しました。', basename($file));
            continue;
        }

        $decoded = json_decode($json, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = sprintf('ファイル「%s」のJSONデコードに失敗しました: %s', basename($file), json_last_error_msg());
            continue;
        }

        if (!is_array($decoded)) {
            $errors[] = sprintf('ファイル「%s」の形式が不正です。配列のJSONを期待しています。', basename($file));
            continue;
        }

        foreach ($decoded as $snippet) {
            if (is_array($snippet)) {
                $snippets[] = $snippet;
            }
        }
    }

    // created_at の降順でソート（存在しない場合は末尾）
    usort($snippets, function (array $a, array $b) {
        $aTime = isset($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
        $bTime = isset($b['created_at']) ? strtotime((string)$b['created_at']) : 0;
        return $bTime <=> $aTime;
    });

    return $snippets;
}

// タグでのAND検索を行う
function filter_snippets_by_tags(array $snippets, array $searchTags): array
{
    if ($searchTags === []) {
        return $snippets;
    }

    $normalizedSearchTags = array_map(function ($tag) {
        return mb_strtolower(trim((string)$tag));
    }, $searchTags);

    return array_values(array_filter($snippets, function (array $snippet) use ($normalizedSearchTags) {
        $snippetTags = array_map(function ($tag) {
            return mb_strtolower((string)$tag);
        }, $snippet['tags'] ?? []);

        foreach ($normalizedSearchTags as $tag) {
            if (!in_array($tag, $snippetTags, true)) {
                return false;
            }
        }
        return true;
    }));
}

// 説明文やコード（コメント含む）に対するキーワード検索
function filter_snippets_by_keyword(array $snippets, string $keyword): array
{
    $trimmedKeyword = trim($keyword);

    if ($trimmedKeyword === '') {
        return $snippets;
    }

    return array_values(array_filter($snippets, function (array $snippet) use ($trimmedKeyword) {
        $haystack = implode('\n', [
            (string)($snippet['title'] ?? ''),
            (string)($snippet['description'] ?? ''),
            (string)($snippet['code'] ?? ''),
        ]);

        return mb_stripos($haystack, $trimmedKeyword) !== false;
    }));
}

// 言語での絞り込み
function filter_snippets_by_language(array $snippets, string $language): array
{
    $normalizedLanguage = trim($language);

    if ($normalizedLanguage === '') {
        return $snippets;
    }

    $normalizedLanguage = mb_strtolower($normalizedLanguage);

    return array_values(array_filter($snippets, function (array $snippet) use ($normalizedLanguage) {
        $language = mb_strtolower((string)($snippet['language'] ?? ''));
        return $language === $normalizedLanguage;
    }));
}

$dataDir = __DIR__ . '/data';
$errorMessages = [];
$snippets = [];

if (!is_dir($dataDir)) {
    $errorMessages[] = 'data ディレクトリが見つかりません。アプリを利用するには data 配下に JSON ファイルを配置してください。';
} else {
    $snippets = load_all_snippets($dataDir, $errorMessages);
}

$tagInputRaw = $_GET['tags'] ?? '';
$searchTags = [];

if (is_array($tagInputRaw)) {
    foreach ($tagInputRaw as $tag) {
        $trimmed = trim((string)$tag);
        if ($trimmed !== '') {
            $searchTags[] = $trimmed;
        }
    }
} else {
    $searchTags = array_values(array_filter(array_map('trim', explode(',', (string)$tagInputRaw)), function ($tag) {
        return $tag !== '';
    }));
}

$keyword = isset($_GET['keyword']) ? (string)$_GET['keyword'] : '';
$language = isset($_GET['language']) ? trim((string)$_GET['language']) : '';

$allTags = [];
foreach ($snippets as $snippet) {
    foreach ($snippet['tags'] ?? [] as $tag) {
        $allTags[] = (string)$tag;
    }
}
$availableTags = array_values(array_unique($allTags));
sort($availableTags, SORT_NATURAL | SORT_FLAG_CASE);

$allLanguages = array_map(function ($snippet) {
    return (string)($snippet['language'] ?? '');
}, $snippets);
$availableLanguages = array_values(array_filter(array_unique($allLanguages), function ($lang) {
    return trim($lang) !== '';
}));
sort($availableLanguages, SORT_NATURAL | SORT_FLAG_CASE);

$filteredSnippets = filter_snippets_by_tags($snippets, $searchTags);
$filteredSnippets = filter_snippets_by_keyword($filteredSnippets, $keyword);
$filteredSnippets = filter_snippets_by_language($filteredSnippets, $language);

$totalSnippets = count($snippets);

$hasSearch = $searchTags !== [] || trim($keyword) !== '' || trim($language) !== '';

if (!$hasSearch) {
    $filteredSnippets = [];
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>サンプルコード集</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <link rel="stylesheet" href="assets/style.css?v=1.0.4">
</head>
<body>
<header class="site-header">
    <div class="container">
        <h1><a href="index.php">サンプルコード集</a></h1>
        <p class="description">
            複数言語のサンプルコードをタグで絞り込み検索できます。（全<?php echo number_format($totalSnippets); ?>件登録）
        </p>
    </div>
</header>

<main class="container">
    <?php if ($errorMessages !== []): ?>
        <div class="alert alert-error" role="alert">
            <ul>
                <?php foreach ($errorMessages as $message): ?>
                    <li><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="app-layout">
        <aside class="search-panel" id="search-panel" aria-label="検索条件">
            <div class="panel-header">
                <div>
                    <p class="eyebrow">Refine</p>
                    <h2>タグ・キーワード</h2>
                </div>
                <button type="button" class="panel-close" aria-label="検索条件を閉じる">&times;</button>
            </div>

            <form method="get" class="search-form">
                <div class="tag-select" aria-label="タグ選択">
                    <?php if ($availableTags === []): ?>
                        <p class="empty">登録済みのタグがありません。</p>
                    <?php else: ?>
                        <button type="button" class="tag-select__control" aria-haspopup="listbox" aria-expanded="false">
                            タグを選択
                        </button>
                        <div class="tag-select__menu" hidden>
                            <div class="tag-select__search">
                                <input type="text" class="tag-select__filter" placeholder="タグを検索...">
                            </div>
                            <div class="tag-select__options" role="listbox" aria-multiselectable="true">
                                <?php foreach ($availableTags as $tag): ?>
                                    <?php
                                        $tagId = 'tag-' . htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '-', $tag), ENT_QUOTES, 'UTF-8');
                                        $isChecked = in_array($tag, $searchTags, true);
                                    ?>
                                    <label for="<?php echo $tagId; ?>" class="tag-option <?php echo $isChecked ? 'is-active' : ''; ?>" data-label="#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="checkbox" id="<?php echo $tagId; ?>" name="tags[]" value="<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                        <span>#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($searchTags !== []): ?>
                    <div class="selected-tags" aria-live="polite">
                        <p class="selected-tags__label">選択中のタグ</p>
                        <div class="selected-tags__chips">
                            <?php foreach ($searchTags as $tag): ?>
                                <button type="button" class="selected-tag" data-tag="<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span>#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span aria-hidden="true">✕</span>
                                </button>
                            <?php endforeach; ?>
                            <button type="button" class="clear-tags">全部はずす</button>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="search-input-row">
                    <label for="language" class="sr-only">言語</label>
                    <select id="language" name="language" class="language-select">
                        <option value="">言語を選択</option>
                        <?php foreach ($availableLanguages as $languageOption): ?>
                            <option value="<?php echo htmlspecialchars($languageOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $languageOption === $language ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($languageOption, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" id="keyword" name="keyword" value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>" placeholder="例: ループ, 配列, comment">
                    <button type="submit">検索</button>
                </div>
            </form>
        </aside>

        <div class="content-column">
            <div class="content-bar">
                <div>
                    <h2>サンプルコード一覧</h2>
                    <p class="subtitle">タグやキーワードで好みのスニペットを素早く見つけられます。</p>
                </div>
                <button type="button" class="filter-toggle" aria-controls="search-panel" aria-expanded="false">
                    <span class="filter-toggle__icon" aria-hidden="true"></span>
                    <span>条件を開く</span>
                </button>
            </div>

            <?php if ($hasSearch): ?>
                <div class="search-summary" aria-live="polite">
                    <p class="search-summary__title">次の条件で絞り込み中:</p>
                    <ul class="search-summary__list">
                        <?php if (trim($language) !== ''): ?>
                            <li class="search-summary__item">
                                <span class="search-summary__label">言語</span>
                                <span class="badge language"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endif; ?>
                        <?php if (trim($keyword) !== ''): ?>
                            <li class="search-summary__item">
                                <span class="search-summary__label">キーワード</span>
                                <span class="search-summary__chip">「<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>」</span>
                            </li>
                        <?php endif; ?>
                        <?php if ($searchTags !== []): ?>
                            <li class="search-summary__item">
                                <span class="search-summary__label">タグ</span>
                                <span class="search-summary__chips">
                                    <?php foreach ($searchTags as $tag): ?>
                                        <span class="tag">#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <section class="snippets">
                <?php if (!$hasSearch): ?>
                    <p class="empty">検索条件を設定してください。</p>
                <?php elseif ($filteredSnippets === []): ?>
                    <p class="empty">条件に一致するスニペットがありません。</p>
                <?php else: ?>
                    <?php foreach ($filteredSnippets as $index => $snippet): ?>
                        <?php
                            $codeId = 'code-' . $index;
                            $title = htmlspecialchars($snippet['title'] ?? '無題', ENT_QUOTES, 'UTF-8');
                            $languageRaw = (string)($snippet['language'] ?? 'N/A');
                            $languageDisplay = htmlspecialchars($languageRaw, ENT_QUOTES, 'UTF-8');
                            $languageKey = strtolower($languageRaw);
                            $highlightLanguage = $languageKey === 'gas' ? 'javascript' : $languageKey;
                            $languageSlug = htmlspecialchars(preg_replace('/[^a-zA-Z0-9\+#-]/', '-', $highlightLanguage) ?: 'plaintext', ENT_QUOTES, 'UTF-8');
                            $description = htmlspecialchars($snippet['description'] ?? '', ENT_QUOTES, 'UTF-8');
                            $createdAt = htmlspecialchars($snippet['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
                            $updatedAt = htmlspecialchars($snippet['updated_at'] ?? '', ENT_QUOTES, 'UTF-8');
                            $tags = $snippet['tags'] ?? [];
                        ?>
                        <article class="snippet">
                            <header class="snippet-header">
                                <div>
                                    <h3><?php echo $title; ?></h3>
                                    <p class="meta">言語: <span class="badge language"><?php echo $languageDisplay; ?></span></p>
                                </div>
                                <div class="timestamps">
                                    <?php if ($createdAt !== ''): ?>
                                        <span class="meta-label">作成: <?php echo $createdAt; ?></span>
                                    <?php endif; ?>
                                    <?php if ($updatedAt !== ''): ?>
                                        <span class="meta-label">更新: <?php echo $updatedAt; ?></span>
                                    <?php endif; ?>
                                </div>
                            </header>

                            <?php if ($description !== ''): ?>
                                <p class="description"><?php echo $description; ?></p>
                            <?php endif; ?>

                            <div class="tags">
                                <?php foreach ($tags as $tag): ?>
                                    <span class="tag">#<?php echo htmlspecialchars((string)$tag, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>

                            <div class="code-block" data-language="<?php echo $languageDisplay; ?>">
                                <pre><code id="<?php echo $codeId; ?>" class="language-<?php echo $languageSlug; ?>"><?php echo htmlspecialchars((string)($snippet['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></pre>
                                <button class="copy-btn" data-target="<?php echo $codeId; ?>">コピー</button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <div class="panel-backdrop" id="panel-backdrop" hidden></div>
</main>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
<script src="assets/script.js?v=1.0.4"></script>
</body>
</html>
