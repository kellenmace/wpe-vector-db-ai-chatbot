<script lang="ts">
  import "deep-chat";
  import ChevronDownIcon from "./components/ChevronDownIcon.svelte";
  import ChatBubbleIcon from "./components/ChatBubbleIcon.svelte";

  // Get the chat endpoint from WordPress localized script
  const chatEndpoint = window.aiChatbot?.chatEndpoint ?? "";

  // Set up the connection configuration
  const connectConfig = {
    url: chatEndpoint,
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    stream: true, // Enable streaming from server-sent events
  };

  // Set up text input configuration
  const textInputConfig = {
    placeholder: { text: "Ask me about TV shows..." },
  };

  // Set up request body limits to manage context
  const requestBodyLimits = {
    maxMessages: 10, // Include last 10 messages for context
    totalMessagesMaxCharLength: 4000, // Limit total character count
  };

  // Set initial history
  const initialHistory = [
    {
      role: "ai",
      text: "Hello! I'm your AI assistant. I can help you find information about TV shows using our comprehensive database. What would you like to know?",
    },
  ];

  let isOpen = $state(false);
</script>

<div class="ai-chatbot-wrap" style={isOpen ? "" : "display: none;"}>
  <deep-chat
    connect={connectConfig}
    textInput={textInputConfig}
    {requestBodyLimits}
    history={initialHistory}
    style="height: 500px; border: none; width: 400px;"
  ></deep-chat>
</div>
<button
  class="ai-chatbot-toggle"
  aria-label="Toggle chatbot"
  onclick={() => (isOpen = !isOpen)}
>
  {#if isOpen}
    <ChevronDownIcon />
  {:else}
    <ChatBubbleIcon />
  {/if}
</button>

<style>
  .ai-chatbot-wrap {
    max-width: 400px;
    position: fixed;
    bottom: 100px;
    right: 20px;
    z-index: 9999;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: rgba(15, 15, 15, 0.4) 0px 5px 40px 0px;
    background: white;
  }

  .ai-chatbot-toggle {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 48px;
    height: 48px;
    background: #2563eb;
    color: white;
    padding: 8px;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow:
      0 1px 6px 0 rgba(0, 0, 0, 0.06),
      0 2px 32px 0 rgba(0, 0, 0, 0.16);
    transition: all 0.2s ease;
  }

  .ai-chatbot-toggle:hover {
    background: #1d4ed8;
    box-shadow:
      0 4px 12px 0 rgba(0, 0, 0, 0.15),
      0 8px 40px 0 rgba(0, 0, 0, 0.2);
  }
</style>
