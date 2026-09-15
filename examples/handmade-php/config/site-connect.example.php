<?php

declare(strict_types=1);

use WeblyConnect\Sdk\Auth\StaticTokenStore;
use WeblyConnect\Sdk\Site\ConnectWidgetUi;
use WeblyConnect\Sdk\Site\SiteConnect;

/**
 * Copy to config/site-connect.php and fill in your tenant values.
 *
 * RAG dossier binding (if any) is configured once on the Hub agent itself
 * (`rag_config.agent_ids` on POST/PATCH https://agenthub.weblyarts.com/agents/{id}),
 * not here. See docs/hub-rag-stack.md in the WeblySuite public docs.
 */
return new SiteConnect(
    tokenStore: new StaticTokenStore('wbly_live_REPLACE_WITH_YOUR_TOKEN'),
    agentId: 'REPLACE_WITH_HUB_AGENT_UUID',
    agentHubBaseUrl: 'https://agenthub.weblyarts.com',
    chatExtra: [],
    widget: new ConnectWidgetUi(
        streamPath: '/stream.php',
        botTitle: 'WeblyConnect',
    ),
);
