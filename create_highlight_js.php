<?php
/**
 * 创建highlight.js文件
 */

$base_dir = '/opt/homebrew/var/www';
$js_file_path = $base_dir . '/highlight.js';

$js_content = <<<'JS'
// highlight.js - 关键字高亮和定位功能
(function() {
    'use strict';
    
    // 获取URL参数
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            highlight: params.get('highlight'),
            line: parseInt(params.get('line')) || 0
        };
    }
    
    // 高亮指定文本
    function highlightText(element, keyword) {
        if (!keyword || !element) return 0;
        
        let count = 0;
        const walker = document.createTreeWalker(
            element,
            NodeFilter.SHOW_TEXT,
            null,
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }
        
        textNodes.forEach((textNode, index) => {
            const text = textNode.textContent;
            const regex = new RegExp(`(${keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            
            if (regex.test(text)) {
                const highlightedHTML = text.replace(regex, (match, p1, offset) => {
                    count++;
                    const id = `highlight_${count}`;
                    return `<span class="search-highlight-target" id="${id}" style="background-color: #ffeb3b; padding: 2px 4px; border-radius: 2px; font-weight: bold; box-shadow: 0 0 5px rgba(255, 235, 59, 0.7);">${p1}</span>`;
                });
                
                const wrapper = document.createElement('span');
                wrapper.innerHTML = highlightedHTML;
                textNode.parentNode.replaceChild(wrapper, textNode);
            }
        });
        
        return count;
    }
    
    // 通过行号定位
    function highlightByLineNumber(lineNumber, keyword) {
        // 尝试多种方式找到对应的行
        const allElements = document.querySelectorAll('*');
        const candidates = [];
        
        // 收集所有包含关键字的元素
        allElements.forEach(element => {
            if (element.textContent && element.textContent.toLowerCase().includes(keyword.toLowerCase())) {
                candidates.push(element);
            }
        });
        
        // 如果找到候选元素，高亮第一个或指定行号附近的
        if (candidates.length > 0) {
            let targetElement = candidates[0];
            
            // 如果有多个候选，尝试找到最合适的
            if (candidates.length > 1 && lineNumber > 0) {
                // 简单的启发式：选择文档中位置相对应的元素
                const targetIndex = Math.min(Math.floor((lineNumber - 1) / 10), candidates.length - 1);
                targetElement = candidates[targetIndex];
            }
            
            // 高亮该元素中的关键字
            const highlightCount = highlightText(targetElement, keyword);
            
            if (highlightCount > 0) {
                // 滚动到第一个高亮的位置
                setTimeout(() => {
                    const firstHighlight = document.getElementById('highlight_1');
                    if (firstHighlight) {
                        firstHighlight.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'center',
                            inline: 'nearest'
                        });
                        
                        // 添加闪烁效果
                        firstHighlight.style.animation = 'search-highlight-flash 2s ease-in-out';
                    }
                }, 100);
                
                return true;
            }
        }
        
        return false;
    }
    
    // 添加CSS动画
    function addHighlightStyles() {
        if (document.getElementById('search-highlight-styles')) return;
        
        const style = document.createElement('style');
        style.id = 'search-highlight-styles';
        style.textContent = `
            @keyframes search-highlight-flash {
                0%, 100% { 
                    background-color: #ffeb3b; 
                    box-shadow: 0 0 5px rgba(255, 235, 59, 0.7);
                }
                50% { 
                    background-color: #ff9800; 
                    box-shadow: 0 0 10px rgba(255, 152, 0, 0.8);
                }
            }
            
            .search-highlight-target {
                transition: all 0.3s ease;
            }
            
            .search-highlight-target:hover {
                background-color: #ff9800 !important;
                transform: scale(1.05);
            }
            
            /* 搜索工具栏样式 */
            .search-toolbar {
                position: fixed;
                top: 10px;
                right: 10px;
                background: rgba(0, 0, 0, 0.8);
                color: white;
                padding: 10px;
                border-radius: 5px;
                font-size: 12px;
                z-index: 10000;
                font-family: Arial, sans-serif;
            }
            
            .search-toolbar button {
                background: #007acc;
                color: white;
                border: none;
                padding: 5px 10px;
                margin: 0 2px;
                border-radius: 3px;
                cursor: pointer;
                font-size: 12px;
            }
            
            .search-toolbar button:hover {
                background: #005fa3;
            }
            
            .search-toolbar button:disabled {
                background: #666;
                cursor: not-allowed;
            }
        `;
        document.head.appendChild(style);
    }
    
    // 创建搜索工具栏
    function createSearchToolbar(keyword, totalCount) {
        if (document.getElementById('search-toolbar')) return;
        
        const toolbar = document.createElement('div');
        toolbar.id = 'search-toolbar';
        toolbar.className = 'search-toolbar';
        
        let currentIndex = 1;
        
        toolbar.innerHTML = `
            <div>找到 "${keyword}": <span id="current-highlight">${currentIndex}</span>/${totalCount}</div>
            <div style="margin-top: 5px;">
                <button id="prev-highlight" ${currentIndex <= 1 ? 'disabled' : ''}>上一个</button>
                <button id="next-highlight" ${currentIndex >= totalCount ? 'disabled' : ''}>下一个</button>
                <button id="close-search">关闭</button>
            </div>
        `;
        
        document.body.appendChild(toolbar);
        
        // 添加事件监听
        document.getElementById('prev-highlight').addEventListener('click', () => {
            if (currentIndex > 1) {
                currentIndex--;
                jumpToHighlight(currentIndex);
                updateToolbar();
            }
        });
        
        document.getElementById('next-highlight').addEventListener('click', () => {
            if (currentIndex < totalCount) {
                currentIndex++;
                jumpToHighlight(currentIndex);
                updateToolbar();
            }
        });
        
        document.getElementById('close-search').addEventListener('click', () => {
            toolbar.remove();
            // 移除所有高亮
            document.querySelectorAll('.search-highlight-target').forEach(el => {
                el.outerHTML = el.textContent;
            });
        });
        
        function updateToolbar() {
            document.getElementById('current-highlight').textContent = currentIndex;
            document.getElementById('prev-highlight').disabled = currentIndex <= 1;
            document.getElementById('next-highlight').disabled = currentIndex >= totalCount;
        }
        
        function jumpToHighlight(index) {
            const highlight = document.getElementById(`highlight_${index}`);
            if (highlight) {
                highlight.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center',
                    inline: 'nearest'
                });
                
                // 重置所有高亮样式
                document.querySelectorAll('.search-highlight-target').forEach(el => {
                    el.style.animation = '';
                });
                
                // 为当前高亮添加动画
                highlight.style.animation = 'search-highlight-flash 1s ease-in-out';
            }
        }
    }
    
    // 主函数
    function initHighlight() {
        const params = getUrlParams();
        
        if (params.highlight) {
            addHighlightStyles();
            
            // 等待页面完全加载
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    setTimeout(() => processHighlight(params), 500);
                });
            } else {
                setTimeout(() => processHighlight(params), 100);
            }
        }
    }
    
    function processHighlight(params) {
        const success = highlightByLineNumber(params.line, params.highlight);
        
        if (success) {
            // 创建搜索工具栏
            const totalHighlights = document.querySelectorAll('.search-highlight-target').length;
            if (totalHighlights > 1) {
                createSearchToolbar(params.highlight, totalHighlights);
            }
        } else {
            // 如果按行号定位失败，尝试全文搜索
            const totalHighlights = highlightText(document.body, params.highlight);
            if (totalHighlights > 0) {
                const firstHighlight = document.getElementById('highlight_1');
                if (firstHighlight) {
                    firstHighlight.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                }
                
                if (totalHighlights > 1) {
                    createSearchToolbar(params.highlight, totalHighlights);
                }
            }
        }
    }
    
    // 初始化
    initHighlight();
})();
JS;

if (file_put_contents($js_file_path, $js_content)) {
    echo "成功创建 highlight.js 文件: $js_file_path\n";
    echo "文件大小: " . number_format(filesize($js_file_path) / 1024, 2) . " KB\n";
} else {
    echo "创建 highlight.js 文件失败!\n";
}
?>
