<?php
/**
 * Copy this file to include/webhook-config.php and fill in real values.
 * The real webhook-config.php is gitignored so secrets never leave the server.
 *
 * All six fields are required for deploy-webhook.php to run.
 */

return [
    // Shared secret configured on the GitHub webhook. Any long random string.
    // Generate one on the command line:  openssl rand -hex 32
    'github_secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_HEX_STRING',

    // Your cPanel login username (e.g. "mayaofla").
    'cpanel_user' => 'CHANGE_ME_CPANEL_USERNAME',

    // The API token value from cPanel > Manage API Tokens (the string that
    // appears after you click Create). Not your cPanel password.
    'cpanel_token' => 'CHANGE_ME_CPANEL_API_TOKEN',

    // The cPanel server host (what you use to reach cPanel, without the
    // :2083 port). Example: "server123.web-hosting.com" or your own domain.
    'cpanel_host' => 'CHANGE_ME_CPANEL_HOST',

    // Absolute path to the git repo clone on the cPanel account. Look this
    // up in cPanel > Git Version Control — it's the "Repository Path" field.
    // Example: "/home/mayaofla/repositories/bankpro-banking-script".
    'repo_root' => '/home/CHANGE_ME_CPANEL_USERNAME/repositories/bankpro-banking-script',

    // Only deploy for pushes to this branch. Other branches are ignored.
    'allowed_branch' => 'production',
];
