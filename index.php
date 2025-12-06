<?php
declare(strict_types=1);

// data ディレクトリを基準に全てのスニペットを読み込む
function load_all_snippets(string $dataDir): array
{
    if (!is_dir($dataDir)) {
        return [];
    }

    $snippets = [];
    $files = glob($dataDir . '/*.json');

    foreach ($files as $file) {
        $json = @file_get_contents($file);
        if ($json === false) {
            // 読み込み失敗時はスキップ
            continue;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            // JSONの形式が配列でない場合はスキップ
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

$dataDir = __DIR__ . '/data';
$errorMessage = '';
$snippets = [];

if (!is_dir($dataDir)) {
    $errorMessage = 'data ディレクトリが見つかりません。アプリを利用するには data 配下に JSON ファイルを配置してください。';
} else {
    $snippets = load_all_snippets($dataDir);
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

$allTags = [];
foreach ($snippets as $snippet) {
    foreach ($snippet['tags'] ?? [] as $tag) {
        $allTags[] = (string)$tag;
    }
}
$availableTags = array_values(array_unique($allTags));
sort($availableTags, SORT_NATURAL | SORT_FLAG_CASE);

$filteredSnippets = filter_snippets_by_tags($snippets, $searchTags);
$filteredSnippets = filter_snippets_by_keyword($filteredSnippets, $keyword);
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
    <link rel="stylesheet" href="assets/style.css?v=1.0.1">
</head>
<body>
<header class="site-header">
    <div class="container">
        <h1>サンプルコード集</h1>
        <p class="description">複数言語のサンプルコードをタグで絞り込み検索できます。</p>
    </div>
</header>

<main class="container">
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
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
                <div class="tag-toggle-group" aria-label="タグ選択">
                    <?php if ($availableTags === []): ?>
                        <p class="empty">登録済みのタグがありません。</p>
                    <?php else: ?>
                        <?php foreach ($availableTags as $tag): ?>
                            <?php
                                $tagId = 'tag-' . htmlspecialchars(preg_replace('/[^a-zA-Z0-9_-]/', '-', $tag), ENT_QUOTES, 'UTF-8');
                                $isChecked = in_array($tag, $searchTags, true);
                            ?>
                            <label for="<?php echo $tagId; ?>" class="tag-toggle <?php echo $isChecked ? 'is-active' : ''; ?>">
                                <input type="checkbox" id="<?php echo $tagId; ?>" name="tags[]" value="<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                <span>#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                            </label>
                        <?php endforeach; ?>
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

            <?php if ($searchTags !== [] || trim($keyword) !== ''): ?>
                <p class="search-summary">次の条件で絞り込み中:
                    <?php if ($searchTags !== []): ?>
                        <span>タグ:</span>
                        <?php foreach ($searchTags as $tag): ?>
                            <span class="tag">#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (trim($keyword) !== ''): ?>
                        <span class="keyword">キーワード: 「<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>」</span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <section class="snippets">
                <?php if ($filteredSnippets === []): ?>
                    <p class="empty">条件に一致するスニペットがありません。</p>
                <?php else: ?>
                    <?php foreach ($filteredSnippets as $index => $snippet): ?>
                        <?php
                            $codeId = 'code-' . $index;
                            $title = htmlspecialchars($snippet['title'] ?? '無題', ENT_QUOTES, 'UTF-8');
                            $language = htmlspecialchars($snippet['language'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
                            $description = htmlspecialchars($snippet['description'] ?? '', ENT_QUOTES, 'UTF-8');
                            $createdAt = htmlspecialchars($snippet['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
                            $updatedAt = htmlspecialchars($snippet['updated_at'] ?? '', ENT_QUOTES, 'UTF-8');
                            $tags = $snippet['tags'] ?? [];
                        ?>
                        <article class="snippet">
                            <header class="snippet-header">
                                <div>
                                    <h3><?php echo $title; ?></h3>
                                    <p class="meta">言語: <span class="badge language"><?php echo $language; ?></span></p>
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

                            <div class="code-block">
                                <pre><code id="<?php echo $codeId; ?>"><?php echo htmlspecialchars((string)($snippet['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></pre>
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

<script src="assets/script.js?v=1.0.1"></script>
</body>
</html>
