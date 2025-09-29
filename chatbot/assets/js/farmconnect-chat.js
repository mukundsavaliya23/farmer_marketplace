// FarmConnect Pro Integrated Chatbot
class FarmConnectChatBot {
  constructor() {
    this.sessionId = this.generateSessionId();
    this.isTyping = false;
    this.messageHistory = [];
    this.maxMessageLength = 1000;
    this.isInterfaceOpen = false;

    this.initializeElements();
    this.bindEvents();
    this.initializeWidget();

    console.log("🌾 FarmConnect Pro ChatBot initialized successfully!");
  }

  initializeElements() {
    this.chatInterface = document.getElementById("chatInterface");
    this.chatMessages = document.getElementById("chatMessages");
    this.chatInput = document.getElementById("chatInput");
    this.sendButton = document.getElementById("sendButton");
    this.typingIndicator = document.getElementById("typingIndicator");
    this.errorMessage = document.getElementById("errorMessage");
    this.inputCounter = document.getElementById("inputCounter");
    this.welcomeSection = document.getElementById("welcomeSection");
    this.chatWidget = document.getElementById("chatWidget");
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

    // Close interface when clicking outside
    this.chatInterface.addEventListener("click", (e) => {
      if (e.target === this.chatInterface) {
        this.closeChatInterface();
      }
    });
  }

  initializeWidget() {
    // Show widget after a delay for better UX
    setTimeout(() => {
      if (this.chatWidget) {
        this.chatWidget.style.display = "block";
      }
    }, 2000);
  }

  generateSessionId() {
    return (
      "farmconnect_" +
      Date.now() +
      "_" +
      Math.random().toString(36).substr(2, 9)
    );
  }

  handleInput() {
    const message = this.chatInput.value;
    const length = message.length;

    // Update character counter
    if (this.inputCounter) {
      this.inputCounter.textContent = `${length}/${this.maxMessageLength}`;
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
      this.showError("I encountered a technical issue. Please try again.");
      this.addMessage(
        "I apologize for the technical difficulty. Please try your question again.",
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
                <div class="message-bot-avatar">
                    <i class="fas fa-robot"></i>
                </div>
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
    this.scrollToBottom();
  }

  hideTyping() {
    this.isTyping = false;
    this.typingIndicator.classList.remove("active");
    this.sendButton.disabled = false;
    this.handleInput();
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

  hideWelcomeMessage() {
    if (this.welcomeSection) {
      this.welcomeSection.style.display = "none";
    }
  }

  scrollToBottom() {
    setTimeout(() => {
      this.chatMessages.scrollTop = this.chatMessages.scrollHeight;
    }, 100);
  }

  focusInput() {
    if (!this.isTyping && window.innerWidth > 768 && this.isInterfaceOpen) {
      this.chatInput.focus();
    }
  }

  // Interface control methods
  openChatInterface() {
    this.chatInterface.classList.add("active");
    this.isInterfaceOpen = true;
    document.body.style.overflow = "hidden";

    // Hide notification dot
    const notificationDot = document.querySelector(".notification-dot");
    if (notificationDot) {
      notificationDot.style.display = "none";
    }

    setTimeout(() => {
      this.focusInput();
    }, 300);
  }

  closeChatInterface() {
    this.chatInterface.classList.remove("active");
    this.isInterfaceOpen = false;
    document.body.style.overflow = "";
  }

  minimizeChatInterface() {
    this.chatInterface.classList.add("minimized");
    setTimeout(() => {
      this.closeChatInterface();
      this.chatInterface.classList.remove("minimized");
    }, 300);
  }
}

// Global functions for HTML onclick events
function openChatInterface() {
  if (window.farmConnectBot) {
    window.farmConnectBot.openChatInterface();
  }
}

function closeChatInterface() {
  if (window.farmConnectBot) {
    window.farmConnectBot.closeChatInterface();
  }
}

function minimizeChatInterface() {
  if (window.farmConnectBot) {
    window.farmConnectBot.minimizeChatInterface();
  }
}

// Initialize chatbot when DOM is loaded
document.addEventListener("DOMContentLoaded", () => {
  window.farmConnectBot = new FarmConnectChatBot();
});

// Handle page unload
window.addEventListener("beforeunload", () => {
  console.log("🌾 FarmConnect Pro ChatBot session ended");
});

console.log("🌾 FarmConnect Pro ChatBot loaded successfully!");
