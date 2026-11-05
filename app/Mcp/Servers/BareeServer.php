<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AskKnowledgeHubTool;
use App\Mcp\Tools\ListBareeToolsTool;
use App\Mcp\Tools\MyPassportTool;
use App\Mcp\Tools\RunBareeToolTool;
use Laravel\Mcp\Server;

/**
 * Lets a person use BAREE from their own AI client (Claude Desktop, Claude Code, others that speak MCP).
 * Everything runs as that person: same permissions, institution rules, privacy redaction and credits.
 */
class BareeServer extends Server
{
    protected string $name = 'BAREE';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MD'
        BAREE is an AI workspace for educational institutions. Use `list_baree_tools` to see the tools this user may use,
        `run_baree_tool` to run one with its required inputs, `ask_knowledge_hub` for questions about the user's institution
        policies (answers cite approved documents), and `my_passport` for the user's AI readiness level.
        Never send student names or personal data in inputs; BAREE redacts them, but avoid sending them at all.
        MD;

    protected array $tools = [
        ListBareeToolsTool::class,
        RunBareeToolTool::class,
        AskKnowledgeHubTool::class,
        MyPassportTool::class,
    ];
}
