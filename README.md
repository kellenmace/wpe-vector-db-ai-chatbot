# AI Chatbot WordPress plugin powered by WP Engine's Managed Vector Database

Once installed and configured, this plugin displays an AI-powered chatbot on the frontend of a WordPress site. A user can submit a message and received a streamed response from [Google Gemini](https://ai.google.dev/). The LLM is given data from the [WP Engine Managed Vector Database](https://wpengine.com/managed-vector-database/) and uses this site data to inform its responses.

This results in an AI chatbot that is knowledgeable about the WordPress site's data and can respond to prompts related to it.

## Set up

1. Set up an account on WP Engine and get a WordPress install running.
1. [Add a Smart Search license](https://wpengine.com/support/wp-engine-smart-search/#Enable). This will enable Smart Search for your site and install and configure the Smart Search WordPress plugin.
1. [Perform a content sync](https://wpengine.com/support/wp-engine-smart-search/#Content_Sync) to push your site's data into WP Engine's Vector Database.
1. [Configure Smart Search](https://wpengine.com/support/wp-engine-smart-search/#Search_configuration) as desired to optimize it for your use case.
1. Clone this AI Chatbot plugin and insert it into your WordPress site's `/plugins` folder.
1. Activate this AI Chatbot plugin.
1. In the WordPress admin, go to `Settings` > `AI Chatbot`. Paste your Google Generative AI API Key into the field and save your changes. You can get an API key [here](https://aistudio.google.com/app/apikey).
1. Visit the frontend of your site to interact with the AI chatbot.

## Local development

1. Clone this plugin.
1. Run `npm install` to install NPM dependencies.
1. Run `npm run build` to generate new `/dist/ai-chatbot.js` and `/dist/ai-chatbot.css` files after you make changes to the Svelte source code.
1. Changes to PHP files will be reflected after each page reload.
