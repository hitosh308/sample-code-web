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

$dataDir = __DIR__ . '/data';
$errorMessage = '';
$snippets = [];

if (!is_dir($dataDir)) {
    $errorMessage = 'data ディレクトリが見つかりません。アプリを利用するには data 配下に JSON ファイルを配置してください。';
} else {
    $snippets = load_all_snippets($dataDir);
}

$tagInput = $_GET['tags'] ?? '';
$searchTags = array_values(array_filter(array_map('trim', explode(',', (string)$tagInput)), function ($tag) {
    return $tag !== '';
}));
$filteredSnippets = filter_snippets_by_tags($snippets, $searchTags);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>サンプルコード集</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container">
        <h1>サンプルコード集</h1>
        <p class="description">複数言語のサンプルコードをタグで絞り込み検索できます。コードはJSONファイルから読み込まれます。</p>
    </div>
</header>

<main class="container">
    <?php if ($errorMessage !== ''): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="search-section">
        <h2>タグ検索</h2>
        <form method="get" class="search-form">
            <label for="tags">タグをカンマ区切りで入力（例: php, for）</label>
            <div class="search-input-row">
                <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars((string)$tagInput, ENT_QUOTES, 'UTF-8'); ?>" placeholder="php, for, 入門">
                <button type="submit">検索</button>
            </div>
        </form>
        <?php if ($searchTags !== []): ?>
            <p class="search-summary">次のタグを含むスニペットを表示中: 
                <?php foreach ($searchTags as $tag): ?>
                    <span class="tag">#<?php echo htmlspecialchars($tag, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </section>

    <section class="snippets">
        <h2>サンプルコード一覧</h2>
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
</main>

<footer class="site-footer">
    <div class="container">
        <p>JSONファイルを追加するだけでスニペットが増えます。閲覧専用アプリです。</p>
    </div>
</footer>

<script src="assets/script.js"></script>
</body>
</html>
