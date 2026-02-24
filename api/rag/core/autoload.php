<?php
/**
 * Autoloader per le classi RAG Core
 * 
 * Include questo file per caricare automaticamente tutte le classi necessarie.
 */

// Carica tutte le classi del core
require_once __DIR__ . '/OpenAIClient.php';
require_once __DIR__ . '/DocumentProcessor.php';
require_once __DIR__ . '/EmbeddingManager.php';
require_once __DIR__ . '/TokenOptimizer.php';
require_once __DIR__ . '/RAGEngine.php'; 