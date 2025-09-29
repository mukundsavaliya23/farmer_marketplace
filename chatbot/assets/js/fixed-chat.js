// Fixed Enhanced FarmBot Pro Widget - NO RESERVED WORDS
class FarmBotWidget {
  constructor() {
    this.sessionId = this.generateSessionId();
    this.isOpen = false;
    this.isTyping = false;
    this.messageHistory = [];
    this.maxLength = 1000;

    this.init();
  }

  init() {
    this.createWidget();
    this.bindEvents();
    this.showWelcome();

    console.log("🌾 FarmBot Pro loaded successfully!");
  }

  createWidget() {
    const widget = document.createElement("div");
    widget.className = "farmbot-widget";
    widget.innerHTML = this.getWidgetHTML();
    document.body.appendChild(widget);

    // Initialize elements after DOM insertion
    setTimeout(() => {
      this.elements = {
        trigger: document.querySelector(".farmbot-trigger"),
        chatInterface: document.querySelector(".farmbot-interface"),
        messages: document.querySelector(".farmbot-messages"),
        input: document.querySelector(".message-input"),
        sendBtn: document.querySelector(".send-btn"),
        closeBtn: document.querySelector(".close-btn"),
        typingIndicator: document.querySelector(".typing-indicator"),
        errorMessage: document.querySelector(".error-message"),
        charCounter: document.querySelector(".char-counter"),
      };
    }, 50);
  }

  getWidgetHTML() {
    return `
            <button class="farmbot-trigger" title="Chat with FarmBot Pro">
                <i class="fas fa-robot"></i>
                <div class="notification-pulse"></div>
            </button>
            
            <div class="farmbot-interface">
                <div class="farmbot-header">
                    <div class="farmbot-info">
                        <div class="farmbot-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="farmbot-details">
                            <h3>FarmBot Pro</h3>
                            <div class="farmbot-status">
                                🟢 Online • Agricultural AI Assistant
                            </div>
                        </div>
                    </div>
                    <div class="farmbot-controls">
                        <button class="control-btn close-btn" title="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="error-message"></div>
                
                <div class="farmbot-messages">
                    <div class="farmbot-welcome">
                        <div class="welcome-icon">🌾</div>
                        <h3 class="welcome-title">Welcome to FarmBot Pro!</h3>
                        <p class="welcome-subtitle">I'm your intelligent agricultural assistant, ready to help with farming guidance, crop recommendations, market prices, and more.</p>
                        
                        <div class="quick-suggestions">
                            <button class="suggestion-btn" data-message="What are the best crops to grow this season?">
                                🌱 What crops should I grow this season?
                            </button>
                            <button class="suggestion-btn" data-message="How can I improve my soil quality naturally?">
                                🌍 How to improve soil quality naturally?
                            </button>
                            <button class="suggestion-btn" data-message="What are current market prices for vegetables?">
                                💰 Current vegetable market prices
                            </button>
                            <button class="suggestion-btn" data-message="Tell me about organic farming practices">
                                🌿 Organic farming practices
                            </button>
                            <button class="suggestion-btn" data-message="How do I manage pests without chemicals?">
                                🐛 Natural pest management
                            </button>
                        </div>
                    </div>
                    
                    <div class="typing-indicator">
                        <div class="bot-mini-avatar">
                            <i class="fas fa-robot"></i>
                        </div>
                        <div class="typing-bubble">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                    </div>
                </div>
                
                <div class="farmbot-input">
                    <div class="input-container">
                        <textarea 
                            class="message-input" 
                            placeholder="Ask me about farming, crops, market prices..."
                            rows="1"
                            maxlength="1000"
                        ></textarea>
                        <button class="send-btn" title="Send message">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                    <div class="input-footer">
                        <span class="char-counter">0/1000</span>
                        <span class="farmbot-branding">Powered by FarmConnect Pro</span>
                    </div>
                </div>
            </div>
        `;
  }

  bindEvents() {
    setTimeout(() => {
      if (!this.elements || !this.elements.trigger) {
        console.error("FarmBot elements not found");
        return;
      }

      // Toggle chat
      this.elements.trigger.addEventListener("click", () => this.toggle());
      this.elements.closeBtn.addEventListener("click", () => this.close());

      // Send message
      this.elements.sendBtn.addEventListener("click", () => this.sendMessage());
      this.elements.input.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });

      // Input handling
      this.elements.input.addEventListener("input", () => this.handleInput());

      // Auto-resize textarea
      this.elements.input.addEventListener("input", () => {
        this.elements.input.style.height = "auto";
        this.elements.input.style.height =
          Math.min(this.elements.input.scrollHeight, 120) + "px";
      });

      // Quick suggestions
      document.querySelectorAll(".suggestion-btn").forEach((btn) => {
        btn.addEventListener("click", (e) => {
          const message = e.target.dataset.message;
          if (message) {
            this.elements.input.value = message;
            this.sendMessage();
          }
        });
      });
    }, 100);
  }

  toggle() {
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  open() {
    this.isOpen = true;
    this.elements.chatInterface.classList.add("active");
    this.elements.trigger.classList.add("active");

    setTimeout(() => {
      this.elements.input.focus();
    }, 100);

    // Hide notification
    const notification = document.querySelector(".notification-pulse");
    if (notification) notification.style.display = "none";
  }

  close() {
    this.isOpen = false;
    this.elements.chatInterface.classList.remove("active");
    this.elements.trigger.classList.remove("active");
  }

  handleInput() {
    const length = this.elements.input.value.length;
    this.elements.charCounter.textContent = `${length}/${this.maxLength}`;

    if (length > this.maxLength * 0.9) {
      this.elements.charCounter.className = "char-counter warning";
    } else if (length > this.maxLength) {
      this.elements.charCounter.className = "char-counter error";
    } else {
      this.elements.charCounter.className = "char-counter";
    }

    this.elements.sendBtn.disabled =
      length === 0 || length > this.maxLength || this.isTyping;
  }

  async sendMessage() {
    const message = this.elements.input.value.trim();
    if (!message || this.isTyping || message.length > this.maxLength) return;

    this.hideWelcome();
    this.hideError();
    this.addMessage(message, "user");

    this.elements.input.value = "";
    this.elements.input.style.height = "auto";
    this.handleInput();

    this.showTyping();

    try {
      const response = await this.callAPI(message);

      if (response.success) {
        this.addMessage(response.message, "bot", response.response_time);
        this.messageHistory.push(
          { role: "user", message },
          { role: "bot", message: response.message }
        );
      } else {
        throw new Error(response.message || "Unknown error");
      }
    } catch (error) {
      console.error("Chat error:", error);
      this.showError("I encountered a technical issue. Please try again.");
      this.addMessage(
        "I apologize for the technical difficulty. Please try again.",
        "bot"
      );
    } finally {
      this.hideTyping();
      this.elements.input.focus();
    }
  }

  async callAPI(message) {
    const response = await fetch("/farmer_marketplace1/chatbot/api/chat.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
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

  addMessage(message, type, responseTime) {
    const messageDiv = document.createElement("div");
    messageDiv.className = `message ${type}`;

    const time = new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });
    const timeText = responseTime ? ` • ${responseTime}ms` : "";

    if (type === "user") {
      messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${this.formatMessage(message)}
                    <div class="message-time">${time}</div>
                </div>
            `;
    } else {
      messageDiv.innerHTML = `
                <div class="bot-mini-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-bubble">
                    ${this.formatMessage(message)}
                    <div class="message-time">${time}${timeText}</div>
                </div>
            `;
    }

    this.elements.messages.appendChild(messageDiv);
    this.scrollToBottom();
  }

  formatMessage(message) {
    message = message.replace(
      /(https?:\/\/[^\s]+)/g,
      '<a href="$1" target="_blank" rel="noopener">$1</a>'
    );
    message = message.replace(/^\• (.+)$/gm, "<li>$1</li>");
    if (message.includes("<li>")) {
      message = message.replace(/((<li>.*<\/li>\s*)+)/gs, "<ul>$1</ul>");
    }
    message = message.replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>");
    message = message.replace(/\*(.*?)\*/g, "<em>$1</em>");
    message = message.replace(/\n/g, "<br>");
    return message;
  }

  showTyping() {
    this.isTyping = true;
    this.elements.typingIndicator.classList.add("active");
    this.elements.sendBtn.disabled = true;
    this.elements.sendBtn.classList.add("sending");
    this.elements.sendBtn.innerHTML = '<i class="fas fa-spinner"></i>';
    this.scrollToBottom();
  }

  hideTyping() {
    this.isTyping = false;
    this.elements.typingIndicator.classList.remove("active");
    this.elements.sendBtn.disabled = false;
    this.elements.sendBtn.classList.remove("sending");
    this.elements.sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
    this.handleInput();
  }

  showWelcome() {
    // Welcome shown by default
  }

  hideWelcome() {
    const welcome = document.querySelector(".farmbot-welcome");
    if (welcome) welcome.style.display = "none";
  }

  showError(message) {
    this.elements.errorMessage.textContent = message;
    this.elements.errorMessage.classList.add("show");
    setTimeout(() => this.hideError(), 5000);
  }

  hideError() {
    this.elements.errorMessage.classList.remove("show");
  }

  scrollToBottom() {
    setTimeout(() => {
      this.elements.messages.scrollTop = this.elements.messages.scrollHeight;
    }, 100);
  }

  generateSessionId() {
    return (
      "farmconnect_" +
      Date.now() +
      "_" +
      Math.random().toString(36).substr(2, 9)
    );
  }
}

// Safe initialization without reserved words
document.addEventListener("DOMContentLoaded", () => {
  try {
    window.farmBot = new FarmBotWidget();
    console.log("🌾 FarmBot Pro initialized successfully!");
  } catch (error) {
    console.error("Error initializing FarmBot:", error);
  }
});
