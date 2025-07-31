<script lang="ts">
  import "deep-chat";

  // Get the chat endpoint from WordPress localized script
  const chatEndpoint = window.aiChatbot?.chatEndpoint ?? "";

  // Set up the connection configuration
  const connectConfig = {
    url: chatEndpoint,
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    stream: true, // Enable actual streaming from server-sent events
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
    <svg
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="m19.5 8.25-7.5 7.5-7.5-7.5"
      />
    </svg>
  {:else}
    <svg
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"
      />
    </svg>
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

  .ai-chatbot-toggle svg {
    width: 60%;
    height: 60%;
  }
</style>
