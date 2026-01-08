jQuery(document).ready(function ($) {
  const $container = $("#exgrip-chatbot");
  const $messagesContainer = $("#exgrip-messages");
  const $input = $("#exgrip-input");
  const $sendBtn = $("#exgrip-send");
  const $minimizeBtn = $("#exgrip-minimize-btn");

  // Store chat history for context window
  let chatHistory = [];

  // Auto-scroll to bottom when new messages arrive
  function scrollToBottom() {
    $messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
  }

  // Add message to chat
  function addMessage(text, isUser = false) {
    const messageClass = isUser ? "user" : "bot";
    const messageHTML = `
            <div class="exgrip-message ${messageClass}">
                <div class="exgrip-message-avatar">${isUser ? "U" : "⚡"}</div>
                <div class="exgrip-message-content">${escapeHtml(text)}</div>
            </div>
        `;
    $messagesContainer.append(messageHTML);

    // Add to chat history
    chatHistory.push({
      role: isUser ? "user" : "model",
      text: text,
    });

    scrollToBottom();
  }

  // Show loading indicator
  function showLoadingIndicator() {
    const loadingHTML = `
            <div class="exgrip-message bot loading">
                <div class="exgrip-message-avatar">⚡</div>
                <div class="exgrip-message-content">
                    <div class="exgrip-typing-indicator">
                        <div class="exgrip-typing-dot"></div>
                        <div class="exgrip-typing-dot"></div>
                        <div class="exgrip-typing-dot"></div>
                    </div>
                </div>
            </div>
        `;
    $messagesContainer.append(loadingHTML);
    scrollToBottom();
    return $messagesContainer.find(".bot.loading:last");
  }

  // Escape HTML for security
  function escapeHtml(text) {
    const map = {
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;",
    };
    return text.replace(/[&<>"']/g, (m) => map[m]);
  }

  // Send message
  function sendMessage() {
    const message = $input.val().trim();
    if (!message) return;

    // Disable input and send button
    $input.val("").focus();
    $sendBtn.prop("disabled", true);

    // Add user message to chat and history
    addMessage(message, true);

    // Show loading indicator
    const $loadingMsg = showLoadingIndicator();

    // Prepare chat history for API (excluding current message since it's added above)
    const historyToSend = chatHistory.slice(0, -1);

    // Send to server
    $.post(
      ExgripAI.ajax_url,
      {
        action: "exgrip_ai_query",
        message: message,
        history: JSON.stringify(historyToSend),
        nonce: ExgripAI.nonce,
      },
      function (response) {
        // Remove loading indicator
        $loadingMsg.remove();

        // Handle response
        if (response.success) {
          const aiMessage = response.data.message;
          addMessage(aiMessage, false);

          // Log token usage if available
          if (response.data.tokens_used) {
            console.log("Tokens used:", response.data.tokens_used);
          }
        } else {
          addMessage("Error: " + (response.data || "Unknown error"), false);
        }
      },
      "json"
    )
      .fail(function (jqxhr, textStatus, errorThrown) {
        $loadingMsg.remove();
        addMessage(
          "Error: Connection failed. Please check your internet connection.",
          false
        );
      })
      .always(function () {
        $sendBtn.prop("disabled", false);
      });
  }

  // Send button click
  $sendBtn.on("click", sendMessage);

  // Enter key to send
  $input.on("keypress", function (e) {
    if (e.which === 13) {
      e.preventDefault();
      sendMessage();
    }
  });

  // Minimize button
  $minimizeBtn.on("click", function () {
    const $chatContainer = $(".exgrip-chatbot-container");
    $chatContainer.toggleClass("minimized");
    const isMinimized = $chatContainer.hasClass("minimized");

    if (isMinimized) {
      // Show chat icon when minimized
      $minimizeBtn.html(
        '<svg viewBox="0 0 24 24" width="28" height="28"><path d="M20 2H4c-1.1 0-2 .9-2 2v18c0 1.1.9 2 2 2h14l4 4V4c0-1.1-.9-2-2-2z" fill="currentColor"/></svg>'
      );
    } else {
      // Show minimize icon when expanded
      $minimizeBtn.html(
        '<svg viewBox="0 0 24 24" width="20" height="20"><line x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>'
      );
    }
  });

  // Focus input on load
  $input.focus();
});
