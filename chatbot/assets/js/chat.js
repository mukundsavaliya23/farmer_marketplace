class FarmBotChat {
  constructor() {
    this.sessionId = this.generateSessionId();
    this.isTyping = false;
    this.messageHistory = [];
    this.maxMessageLength = 1000;

    this.initializeElements();
    this.bindEvents();
    this.showWelcomeMessage();
    this.focusInput();

    console.log("🌾 FarmBot Pro initialized successfully!");
  }

  initializeElements() {
    this.chatMessages = document.getElementById("chatMessages");
    this.chatInput = document.getElementById("chatInput");
    this.sendButton = document.getElementById("sendButton");
    this.typingIndicator = document.getElementById("typingIndicator");
    this.errorMessage = document.getElementById("errorMessage");
    this.inputCounter = document.getElementById("inputCounter");
    this.statusIndicator = document.querySelector(".status-indicator");
    this.welcomeSection = document.getElementById("welcomeSection");
  }

  bindEvents() {
    // Send message events
    this.sendButton.addEventListener("click", () => this.sendMessage());

    this.chatInput.addEventListener("keypress", (e) => {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        this.sendMessage();
      }
    });

    // Input events
    this.chatInput.addEventListener("input", () => this.handleInput());
    this.chatInput.addEventListener("paste", () => {
      setTimeout(() => this.handleInput(), 10);
    });

    // Quick action events
    document.querySelectorAll(".quick-action").forEach((button) => {
      button.addEventListener("click", (e) => {
        const message = e.target.dataset.message;
        if (message) {
          this.chatInput.value = message;
          this.sendMessage();
        }
      });
    });

    // Auto-resize textarea
    this.chatInput.addEventListener("input", () => {
      this.chatInput.style.height = "auto";
      this.chatInput.style.height =
        Math.min(this.chatInput.scrollHeight, 120) + "px";
    });

    // Handle window focus
    window.addEventListener("focus", () => this.focusInput());
  }

  generateSessionId() {
    return (
      "session_" + Date.now() + "_" + Math.random().toString(36).substr(2, 9)
    );
  }

  showWelcomeMessage() {
    if (this.welcomeSection) {
      this.welcomeSection.style.display = "block";
    }
  }

  hideWelcomeMessage() {
    if (this.welcomeSection) {
      this.welcomeSection.style.display = "none";
    }
  }

  handleInput() {
    const message = this.chatInput.value;
    const length = message.length;

    // Update character counter
    if (this.inputCounter) {
      this.inputCounter.textContent = `${length}/${this.maxMessageLength}`;

      if (length > this.maxMessageLength * 0.9) {
        this.inputCounter.className = "input-counter warning";
      } else if (length > this.maxMessageLength) {
        this.inputCounter.className = "input-counter error";
      } else {
        this.inputCounter.className = "input-counter";
      }
    }

    // Enable/disable send button
    this.sendButton.disabled =
      length === 0 || length > this.maxMessageLength || this.isTyping;
  }

  async sendMessage() {
    const message = this.chatInput.value.trim();

    if (!message || this.isTyping || message.length > this.maxMessageLength) {
      return;
    }

    // Hide welcome message and error
    this.hideWelcomeMessage();
    this.hideError();

    // Add user message to chat
    this.addMessage(message, "user");

    // Clear input and reset height
    this.chatInput.value = "";
    this.chatInput.style.height = "auto";
    this.handleInput();

    // Show typing indicator
    this.showTyping();

    try {
      const response = await this.callChatAPI(message);

      if (response.success) {
        this.addMessage(response.message, "bot", response.response_time);
        this.messageHistory.push(
          { role: "user", message: message },
          { role: "bot", message: response.message }
        );
      } else {
        throw new Error(response.message || "Unknown error occurred");
      }
    } catch (error) {
      console.error("Chat error:", error);
      this.showError("Sorry, I encountered an issue. Please try again.");
      this.addMessage(
        "I apologize, but I encountered a technical issue. Please try again in a moment.",
        "bot"
      );
    } finally {
      this.hideTyping();
      this.focusInput();
    }
  }

  async callChatAPI(message) {
    const response = await fetch("api/chat.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        message: message,
        session_id: this.sessionId,
      }),
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    return await response.json();
  }

  addMessage(message, type, responseTime = null) {
    const messageDiv = document.createElement("div");
    messageDiv.className = `message message-${type}`;

    const currentTime = new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });

    if (type === "user") {
      messageDiv.innerHTML = `
                <div class="message-content">
                    ${this.formatMessage(message)}
                    <div class="message-time">${currentTime}</div>
                </div>
            `;
    } else {
      const responseTimeText = responseTime ? ` • ${responseTime}ms` : "";
      messageDiv.innerHTML = `
                <div class="message-bot-avatar">🤖</div>
                <div class="message-content">
                    ${this.formatMessage(message)}
                    <div class="message-time">${currentTime}${responseTimeText}</div>
                </div>
            `;
    }

    this.chatMessages.appendChild(messageDiv);
    this.scrollToBottom();

    // Animate message appearance
    setTimeout(() => {
      messageDiv.style.opacity = "1";
    }, 50);
  }

  formatMessage(message) {
    // Convert URLs to clickable links
    message = message.replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
    );

    // Format lists
    message = message.replace(/^\• (.+)$/gm, "<li>$1</li>");

    if (message.includes("<li>")) {
      message = message.replace(/((<li>.*<\/li>\s*)+)/gs, "<ul>$1</ul>");
    }

    // Format bold text
    message = message.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");

    // Format italic text
    message = message.replace(/\*(.*?)\*/g, "<em>$1</em>");

    // Convert line breaks
    message = message.replace(/\n/g, "<br>");

    return message;
  }

  showTyping() {
    this.isTyping = true;
    this.typingIndicator.classList.add("active");
    this.sendButton.disabled = true;
    this.sendButton.classList.add("sending");
    this.sendButton.innerHTML = '<i class="fas fa-spinner"></i>';
    this.scrollToBottom();
  }

  hideTyping() {
    this.isTyping = false;
    this.typingIndicator.classList.remove("active");
    this.sendButton.disabled = false;
    this.sendButton.classList.remove("sending");
    this.sendButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
    this.handleInput(); // Re-evaluate send button state
  }

  showError(message) {
    if (this.errorMessage) {
      this.errorMessage.textContent = message;
      this.errorMessage.classList.add("show");
      setTimeout(() => this.hideError(), 5000);
    }
  }

  hideError() {
    if (this.errorMessage) {
      this.errorMessage.classList.remove("show");
    }
  }

  scrollToBottom() {
    setTimeout(() => {
      this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }, 100);
  }

  focusInput() {
    if (!this.isTyping && window.innerWidth > 768) {
      this.chatInput.focus();
    }
  }

  // Public methods for external use
  clearChat() {
    this.chatMessages.innerHTML = "";
    this.messageHistory = [];
    this.showWelcomeMessage();
  }

  getSessionId() {
    return this.sessionId;
  }

  getMessageHistory() {
    return this.messageHistory;
  }
}

// Initialize chatbot when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.farmBot = new FarmBotChat();
});

// Handle page unload
window.addEventListener("beforeunload", () => {
  console.log("🌾 FarmBot Pro session ended");
});
