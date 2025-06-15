#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
中文词频统计工具
分析指定目录下HTML文件中的中文词频
"""

import os
import re
import jieba
from collections import Counter
from bs4 import BeautifulSoup
import argparse
import json

def extract_text_from_html(file_path):
    """从HTML文件中提取纯文本内容"""
    try:
        with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
            soup = BeautifulSoup(f.read(), 'html.parser')
            # 移除script和style标签
            for script in soup(["script", "style"]):
                script.decompose()
            return soup.get_text()
    except:
        return ""

def clean_and_segment_text(text):
    """清理文本并进行中文分词"""
    # 只保留中文字符
    chinese_text = re.sub(r'[^\u4e00-\u9fff]', ' ', text)
    # 分词并过滤单字和停用词
    words = [word.strip() for word in jieba.cut(chinese_text) 
             if len(word.strip()) > 1 and word.strip() not in STOP_WORDS]
    return words

def analyze_directory(directory, min_freq=2, top_n=100):
    """分析目录下所有HTML文件的中文词频"""
    word_counter = Counter()
    file_count = 0
    
    print(f"正在分析目录: {directory}")
    
    # 遍历目录下的所有HTML文件
    for root, dirs, files in os.walk(directory):
        for file in files:
            if file.lower().endswith(('.html', '.htm')):
                file_path = os.path.join(root, file)
                text = extract_text_from_html(file_path)
                if text:
                    words = clean_and_segment_text(text)
                    word_counter.update(words)
                    file_count += 1
                    if file_count % 10 == 0:
                        print(f"已处理 {file_count} 个文件...")
    
    print(f"共处理 {file_count} 个HTML文件")
    
    # 过滤低频词并获取Top N
    filtered_words = {word: count for word, count in word_counter.items() 
                     if count >= min_freq}
    top_words = Counter(filtered_words).most_common(top_n)
    
    return top_words, len(word_counter), file_count

def save_results(results, total_words, file_count, output_file):
    """保存分析结果"""
    data = {
        "统计信息": {
            "处理文件数": file_count,
            "总词汇数": total_words,
            "高频词数": len(results)
        },
        "词频统计": [{"词汇": word, "频次": count} for word, count in results]
    }
    
    with open(output_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    
    print(f"结果已保存到: {output_file}")

def print_results(results, total_words, file_count):
    """打印分析结果"""
    print(f"\n{'='*50}")
    print(f"📊 中文词频统计报告")
    print(f"{'='*50}")
    print(f"处理文件数: {file_count}")
    print(f"总词汇数: {total_words}")
    print(f"高频词数: {len(results)}")
    print(f"{'='*50}")
    
    print(f"{'排名':<4} {'词汇':<20} {'频次':<8} {'占比'}")
    print("-" * 50)
    
    total_freq = sum(count for _, count in results)
    for i, (word, count) in enumerate(results[:20], 1):
        percentage = (count / total_freq) * 100
        print(f"{i:<4} {word:<20} {count:<8} {percentage:.2f}%")

# 常用停用词列表
STOP_WORDS = {
    '的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '一个', 
    '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好',
    '自己', '这', '那', '里', '后', '什么', '这个', '怎么', '可以', '没', '能',
    '而且', '但是', '因为', '所以', '如果', '虽然', '然后', '或者', '比较', '非常',
    '已经', '还是', '应该', '可能', '需要', '通过', '进行', '实现', '提供', '使用',
    '包括', '主要', '基本', '一般', '特别', '尤其', '例如', '比如', '这样', '那样'
}

def main():
    parser = argparse.ArgumentParser(description='中文词频统计工具')
    parser.add_argument('directory', nargs='?', default='/opt/homebrew/var/www',
                       help='要分析的目录路径')
    parser.add_argument('-m', '--min-freq', type=int, default=2,
                       help='最小词频阈值 (默认: 2)')
    parser.add_argument('-n', '--top-n', type=int, default=100,
                       help='显示前N个高频词 (默认: 100)')
    parser.add_argument('-o', '--output', default='word_frequency.json',
                       help='输出文件名 (默认: word_frequency.json)')
    
    args = parser.parse_args()
    
    if not os.path.exists(args.directory):
        print(f"错误: 目录 '{args.directory}' 不存在")
        return
    
    # 执行词频分析
    results, total_words, file_count = analyze_directory(
        args.directory, args.min_freq, args.top_n)
    
    if not results:
        print("未找到任何中文内容或HTML文件")
        return
    
    # 显示结果
    print_results(results, total_words, file_count)
    
    # 保存结果
    save_results(results, total_words, file_count, args.output)

if __name__ == "__main__":
    main()
