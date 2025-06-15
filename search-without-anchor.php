<?php
// 设置页面编码为UTF-8，确保中文支持
header('Content-Type: text/html; charset=UTF-8');

// 搜索根目录
$base_dir = '/opt/homebrew/var/www';
$base_url = 'http://127.0.0.1:8080';

// 获取搜索关键词
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$results = [];

// 执行搜索
if (!empty($keyword)) {
    // 使用shell命令进行中文搜索，添加LC_ALL确保中文支持，并限制只搜索HTML文件
    $escaped_keyword = escapeshellarg($keyword);
    $command = "cd " . escapeshellarg($base_dir) . " && LC_ALL=en_US.UTF-8 grep -nr --include='*.html' --include='*.htm' $escaped_keyword * 2>/dev/null";
    
    exec($command, $output, $return_code);
    
    foreach ($output as $line) {
        // 解析grep输出：文件路径:行号:内容
        $parts = explode(':', $line, 3);
        if (count($parts) >= 3) {
            $file_path = $parts[0];
            $line_number = $parts[1];
            $content = trim($parts[2]);
        }
            
            // 检查文件是否存在且可读
            $full_path = $base_dir . '/' . $file_path;
            if (file_exists($full_path) && is_readable($full_path)) {
                // 构建访问URL，添加锚点参数
                $anchor = 'match_' . $line_number; // 使用行号作为锚点标识
                $file_url = $base_url . '/' . $file_path . '#' . $anchor;
                
                // 检查内容长度，如果超过200个字符，则提取关键词周围的上下文
                $content_length = mb_strlen($content, 'UTF-8');
                if ($content_length > 200) {
                    // 查找关键词在内容中的位置
                    $keyword_pos = mb_stripos($content, $keyword, 0, 'UTF-8');
                    if ($keyword_pos !== false) {
                        // 计算截取的起始和结束位置，扩展到前后80个字符
                        $start_pos = max(0, $keyword_pos - 80);
                        $end_pos = min($content_length, $keyword_pos + mb_strlen($keyword, 'UTF-8') + 80);
                        
                        // 提取上下文内容
                        $context = mb_substr($content, $start_pos, $end_pos - $start_pos, 'UTF-8');
                        
                        // 如果不是从开头截取，添加省略号
                        if ($start_pos > 0) {
                            $context = '...' . $context;
                        }
                        // 如果不是截取到末尾，添加省略号
                        if ($end_pos < $content_length) {
                            $context .= '...';
                        }
                        
                        $content = $context;
                    }
                }
                $results[] = [
                    'file_path' => $file_path,
                    'file_url' => $file_url,
                    'line_number' => $line_number,
                    'content' => $content,
                    'file_size' => filesize($full_path),
                ];
            }
    }
}

// 去重并按文件路径排序
$results = array_unique($results, SORT_REGULAR);
usort($results, function($a, $b) {
    return strcmp($a['file_path'], $b['file_path']);
});
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件内容搜索工具</title>
    <style>
        /* 强制重置所有样式 - 使用!important确保覆盖任何继承的样式 */
        html, body, div, span, h1, h2, h3, p, a, form, input, button, ul, li {
            margin: 0 !important;
            padding: 0 !important;
            border: 0 !important;
            font-size: 100% !important;
            font: inherit !important;
            vertical-align: baseline !important;
            box-sizing: border-box !important;
        }
        
        /* 确保html和body占满全屏 */
        html {
            width: 100vw !important;
            min-height: 100vh !important;
            overflow-x: hidden !important;
        }
        
        body {
            width: 100vw !important;
            min-height: 100vh !important;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif !important;
            background-color: #f5f5f5 !important;
            line-height: 1.6 !important;
            padding: 0 !important;
            margin: 0 !important;
            position: relative !important;
        }
        
        /* 创建一个全新的隔离容器 */
        .search-page-wrapper {
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            width: 100vw !important;
            min-height: 100vh !important;
            background-color: #f5f5f5 !important;
            z-index: 9999 !important;
            padding: 20px !important;
            box-sizing: border-box !important;
        }
        
        .search-container {
            width: 100% !important;
            max-width: 1200px !important;
            margin: 0 auto !important;
            background: white !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
            padding: 30px !important;
            box-sizing: border-box !important;
            position: relative !important;
        }
        
        .search-title {
            color: #333 !important;
            text-align: center !important;
            margin-bottom: 30px !important;
            border-bottom: 2px solid #007acc !important;
            padding-bottom: 10px !important;
            font-size: 28px !important;
            font-weight: bold !important;
        }
        
        .search-form-wrapper {
            display: flex !important;
            gap: 10px !important;
            margin-bottom: 30px !important;
            width: 100% !important;
        }
        
        .search-input-field {
            flex: 1 !important;
            padding: 12px 15px !important;
            border: 2px solid #ddd !important;
            border-radius: 6px !important;
            font-size: 16px !important;
            outline: none !important;
            transition: border-color 0.3s !important;
        }
        
        .search-input-field:focus {
            border-color: #007acc !important;
        }
        
        .search-submit-button {
            padding: 12px 25px !important;
            background: #007acc !important;
            color: white !important;
            border: none !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            font-size: 16px !important;
            white-space: nowrap !important;
        }
        
        .search-submit-button:hover {
            background: #005fa3 !important;
        }
        
        .search-results-info {
            margin-bottom: 20px !important;
            padding: 15px !important;
            background: #e7f3ff !important;
            border-left: 4px solid #007acc !important;
            border-radius: 4px !important;
        }
        
        .search-result-item {
            background: #fafafa !important;
            border: 1px solid #e0e0e0 !important;
            border-radius: 6px !important;
            margin-bottom: 15px !important;
            padding: 20px !important;
            width: 100% !important;
            box-sizing: border-box !important;
        }
        
        .search-result-item:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
        }
        
        .search-file-path {
            font-weight: bold !important;
            color: #007acc !important;
            font-size: 16px !important;
            margin-bottom: 8px !important;
            word-break: break-all !important;
        }
        
        .search-file-path a {
            color: #007acc !important;
            text-decoration: none !important;
        }
        
        .search-file-path a:hover {
            text-decoration: underline !important;
        }
        
        .search-file-info {
            color: #666 !important;
            font-size: 14px !important;
            margin-bottom: 10px !important;
        }
        
        .search-content-preview {
            background: white !important;
            border: 1px solid #ddd !important;
            border-radius: 4px !important;
            padding: 12px !important;
            font-family: 'Consolas', 'Monaco', monospace !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
            white-space: pre-wrap !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-all !important;
            width: 100% !important;
            box-sizing: border-box !important;
            overflow-x: auto !important;
        }
        
        .search-highlight {
            background-color: #ffeb3b !important;
            padding: 2px 4px !important;
            border-radius: 2px !important;
            font-weight: bold !important;
        }
        
        .search-no-results {
            text-align: center !important;
            color: #666 !important;
            padding: 50px 20px !important;
            font-size: 18px !important;
        }
        
        .search-tips {
            background: #fff3cd !important;
            border: 1px solid #ffeaa7 !important;
            border-radius: 6px !important;
            padding: 15px !important;
            margin-top: 20px !important;
            color: #856404 !important;
        }
        
        .search-tips h3 {
            margin-top: 0 !important;
            margin-bottom: 10px !important;
            color: #856404 !important;
            font-size: 18px !important;
            font-weight: bold !important;
        }
        
        .search-tips ul {
            margin-left: 20px !important;
            list-style: disc !important;
        }
        
        .search-tips li {
            margin-bottom: 5px !important;
            line-height: 1.5 !important;
        }
    </style>
</head>
<body>
    <div class="search-page-wrapper">
        <div class="search-container">
            <h1 class="search-title">📁 文件内容搜索工具</h1>
            
            <form method="GET" class="search-form-wrapper">
                <input type="text" 
                       name="keyword" 
                       value="<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>" 
                       placeholder="输入中文关键词进行搜索..." 
                       class="search-input-field"
                       required>
                <button type="submit" class="search-submit-button">🔍 搜索</button>
            </form>
            
            <?php if (!empty($keyword)): ?>
                <div class="search-results-info">
                    <strong>搜索关键词：</strong><?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>
                    <br>
                    <strong>找到结果：</strong><?php echo count($results); ?> 个文件
                </div>
                
                <?php if (!empty($results)): ?>
                    <?php foreach ($results as $result): ?>
                        <div class="search-result-item">
                            <div class="search-file-path">
                                <a href="<?php echo htmlspecialchars($result['file_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                                    📄 <?php echo htmlspecialchars($result['file_path'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </div>
                            
                            <div class="search-file-info">
                                <?php if (!empty($result['line_number'])): ?>
                                    行号: <?php echo $result['line_number']; ?> |
                                <?php endif; ?>
                                文件大小: <?php echo number_format($result['file_size'] / 1024, 2); ?> KB
                            </div>
                            
                            <?php if (!empty($result['content'])): ?>
                                <div class="search-content-preview">
<?php 
// 高亮显示关键词
$highlighted_content = htmlspecialchars($result['content'], ENT_QUOTES, 'UTF-8');
if (!empty($keyword)) {
    $highlighted_content = preg_replace('/(' . preg_quote(htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'), '/') . ')/iu', '<span class="search-highlight">$1</span>', $highlighted_content);
}
echo $highlighted_content;
?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="search-no-results">
                        😔 没有找到包含关键词 "<?php echo htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'); ?>" 的文件
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="search-tips">
                <h3>💡 使用提示</h3>
                <ul>
                    <li>支持中文关键词搜索</li>
                    <li>搜索范围：<?php echo $base_dir; ?> 目录下的所有HTML文件</li>
                    <li>点击文件路径可以直接访问文件内容</li>
                    <li>搜索结果会显示包含关键词的行号和内容预览</li>
                    <li>支持大小写不敏感搜索</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="jquery-3.6.1.min.js"></script>
    <script>
    // 锚点跳转功能
    $(document).ready(function() {
        var hash = window.location.hash;
        if (hash) {
            var targetId = hash.substring(1);
            var targetElement = document.getElementById(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                $(targetElement).css('background-color', '#ffeb3b');
                setTimeout(function() {
                    $(targetElement).css('background-color', '');
                }, 2000);
            }
        }
    });
    </script>
</body>
</html>
