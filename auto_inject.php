<?php
/**
 * 自动为HTML文件注入高亮脚本
 * 运行这个脚本会为所有HTML文件添加关键字高亮功能
 */

// 配置
$base_dir = '/opt/homebrew/var/www';
$script_url = '/highlight.js'; // 相对于web根目录的路径

// 要注入的脚本标签
$script_tag = '<script src="' . $script_url . '"></script>';

// 递归扫描目录
function scanDirectory($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), ['html', 'htm'])) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

// 检查文件是否已经包含脚本
function hasScript($content, $script_url) {
    return strpos($content, $script_url) !== false;
}

// 注入脚本到HTML文件
function injectScript($file_path, $script_tag) {
    $content = file_get_contents($file_path);
    
    if (hasScript($content, '/highlight.js')) {
        return "已存在: $file_path\n";
    }
    
    // 尝试在</body>标签前插入
    if (preg_match('/<\/body>/i', $content)) {
        $content = preg_replace('/<\/body>/i', $script_tag . "\n</body>", $content, 1);
    } 
    // 如果没有</body>，尝试在</html>前插入
    elseif (preg_match('/<\/html>/i', $content)) {
        $content = preg_replace('/<\/html>/i', $script_tag . "\n</html>", $content, 1);
    } 
    // 如果都没有，直接在文件末尾添加
    else {
        $content .= "\n" . $script_tag;
    }
    
    // 备份原文件
    $backup_file = $file_path . '.backup.' . date('Y-m-d-H-i-s');
    copy($file_path, $backup_file);
    
    // 写入修改后的内容
    if (file_put_contents($file_path, $content)) {
        return "已注入: $file_path (备份: $backup_file)\n";
    } else {
        return "失败: $file_path\n";
    }
}

// 主执行逻辑
echo "开始扫描目录: $base_dir\n";
echo "==========================================\n";

$html_files = scanDirectory($base_dir);
echo "找到 " . count($html_files) . " 个HTML文件\n\n";

$success_count = 0;
$skip_count = 0;
$error_count = 0;

foreach ($html_files as $file) {
    $result = injectScript($file, $script_tag);
    echo $result;
    
    if (strpos($result, '已注入:') === 0) {
        $success_count++;
    } elseif (strpos($result, '已存在:') === 0) {
        $skip_count++;
    } else {
        $error_count++;
    }
}

echo "\n==========================================\n";
echo "处理完成!\n";
echo "成功注入: $success_count 个文件\n";
echo "已存在脚本: $skip_count 个文件\n";
echo "处理失败: $error_count 个文件\n";

// 检查highlight.js文件是否存在
$highlight_js_path = $base_dir . '/highlight.js';
if (!file_exists($highlight_js_path)) {
    echo "\n注意: highlight.js 文件不存在!\n";
    echo "请将 highlight.js 文件放置到: $highlight_js_path\n";
    echo "或者运行以下命令创建文件:\n";
    echo "php create_highlight_js.php\n";
}
?>
