// Professional Chatbot with Perfect Error Handling
class ProfessionalFarmingChatbot {
  constructor() {
    this.isOpen = false;
    this.apiUrl = "api/chatbot.php";
    this.isTyping = false;
    this.messageQueue = [];
    this.retryCount = 0;
    this.maxRetries = 3;
    this.init();
  }

  init() {
    this.setupEventListeners();
    this.addWelcomeMessage();
    this.setupKeyboardShortcuts();
  }

  setupEventListeners() {
    const chatInput = document.getElementById("chat-input");
    if (chatInput) {
      chatInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter" && !e.shiftKey) {
          e.preventDefault();
          this.sendMessage();
        }
      });

      chatInput.addEventListener("input", () => {
        this.adjustInputHeight();
      });
    }

    // Auto-open chatbot after 5 seconds
    setTimeout(() => {
      if (!this.isOpen) {
        this.showWelcomePrompt();
      }
    }, 5000);
  }

  setupKeyboardShortcuts() {
    document.addEventListener("keydown", (e) => {
      // Ctrl/Cmd + Enter to toggle chatbot
      if ((e.ctrlKey || e.metaKey) && e.key === "Enter") {
        this.toggleChatbot();
      }
    });
  }

  addWelcomeMessage() {
    setTimeout(() => {
      const welcomeMessage = `🌾 **नमस्ते! Welcome to FarmConnect Pro AI Assistant!**

मैं आपका कृषि सलाहकार हूँ। I can help you with:

🌱 **Crop Guidance**: बीज से फसल तक पूरी जानकारी
🐛 **Pest Control**: कीट-रोग का प्राकृतिक इलाज
💰 **Market Prices**: बाजार भाव और बेचने का सही समय
🌤️ **Weather Tips**: मौसम के अनुसार खेती की सलाह
🚜 **Modern Farming**: नई तकनीक और उपकरण
🏛️ **Government Schemes**: सरकारी योजना और सब्सिडी

**Quick Commands:**
• Type "price" for market rates
• Type "weather" for farming tips
• Type "pest" for pest control
• Type "crops" for cultivation advice

किसान भाई/बहन, आज आप क्या जानना चाहते हैं? 🤔`;

      this.addMessage(welcomeMessage, "bot");
    }, 1000);
  }

  showWelcomePrompt() {
    const prompt = document.createElement("div");
    prompt.className = "chatbot-welcome-prompt";
    prompt.innerHTML = `
            <div class="welcome-prompt-content">
                <i class="fas fa-robot"></i>
                <p>Need farming help? Ask our AI assistant!</p>
                <button onclick="farmingChatbot.toggleChatbot()" class="btn-try-now">
                    Try Now <i class="fas fa-arrow-right"></i>
                </button>
                <button onclick="this.parentElement.parentElement.remove()" class="btn-close-prompt">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

    document.body.appendChild(prompt);

    setTimeout(() => {
      prompt.classList.add("show");
    }, 100);

    // Auto hide after 10 seconds
    setTimeout(() => {
      if (prompt.parentElement) {
        prompt.remove();
      }
    }, 10000);
  }

  toggleChatbot() {
    const chatbotBody = document.querySelector(".chatbot-body");
    const toggleIcon = document.querySelector(".toggle-icon");

    this.isOpen = !this.isOpen;

    if (this.isOpen) {
      chatbotBody.classList.add("open");
      toggleIcon.classList.remove("fa-chevron-up");
      toggleIcon.classList.add("fa-chevron-down");
      document.getElementById("chat-input")?.focus();
    } else {
      chatbotBody.classList.remove("open");
      toggleIcon.classList.remove("fa-chevron-down");
      toggleIcon.classList.add("fa-chevron-up");
    }
  }

  async sendMessage(message = null) {
    if (this.isTyping) return;

    const chatInput = document.getElementById("chat-input");
    const messageText = message || chatInput?.value?.trim();

    if (!messageText) return;

    this.addMessage(messageText, "user");
    if (!message && chatInput) chatInput.value = "";
    this.adjustInputHeight();

    this.isTyping = true;
    this.showTypingIndicator();

    try {
      console.log("Sending message:", messageText);

      const response = await fetch(this.apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify({ message: messageText }),
      });

      console.log("Response status:", response.status);
      console.log("Response headers:", response.headers);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const responseText = await response.text();
      console.log("Raw response:", responseText);

      let data;
      try {
        data = JSON.parse(responseText);
      } catch (e) {
        throw new Error("Invalid JSON response from server");
      }

      this.removeTypingIndicator();

      if (data.success && data.response) {
        this.retryCount = 0;
        setTimeout(() => {
          this.addMessage(data.response, "bot");
          this.isTyping = false;
        }, 500);

        // Show helpful suggestions after response
        setTimeout(() => {
          this.showQuickSuggestions();
        }, 2000);
      } else {
        throw new Error(data.message || "No response from AI assistant");
      }
    } catch (error) {
      console.error("Chatbot error:", error);
      this.removeTypingIndicator();
      this.isTyping = false;

      if (this.retryCount < this.maxRetries) {
        this.retryCount++;
        this.addMessage(
          `🔄 Connection issue... Retrying (${this.retryCount}/${this.maxRetries})`,
          "bot"
        );

        setTimeout(() => {
          this.sendMessage(messageText);
        }, 2000);
      } else {
        this.retryCount = 0;
        this.addMessage(
          `❌ **Connection Problem**\n\n` +
            `I'm having trouble connecting to my AI brain right now. This could be because:\n\n` +
            `• Internet connectivity issues\n` +
            `• Server maintenance in progress\n` +
            `• High traffic on our servers\n\n` +
            `**Please try again in a few minutes.** 🙏\n\n` +
            `In the meantime, you can:\n` +
            `• Browse our farmer resources\n` +
            `• Contact our support team\n` +
            `• Check our FAQ section\n\n` +
            `Thank you for your patience! 🌾`,
          "bot"
        );
      }
    }
  }

  sendQuickMessage(message) {
    if (!this.isOpen) {
      this.toggleChatbot();
    }
    setTimeout(() => {
      this.sendMessage(message);
    }, 300);
  }

  addMessage(message, sender) {
    const chatMessages = document.getElementById("chat-messages");
    if (!chatMessages) return;

    const messageDiv = document.createElement("div");
    messageDiv.className = `message ${sender}`;

    const messageContent = document.createElement("div");
    messageContent.className = "message-content";

    const formattedMessage = this.formatMessage(message);
    messageContent.innerHTML = formattedMessage;

    const messageAvatar = document.createElement("div");
    messageAvatar.className = "message-avatar";
    messageAvatar.innerHTML =
      sender === "user"
        ? '<i class="fas fa-user"></i>'
        : '<i class="fas fa-robot"></i>';

    const messageTime = document.createElement("div");
    messageTime.className = "message-time";
    messageTime.textContent = new Date().toLocaleTimeString([], {
      hour: "2-digit",
      minute: "2-digit",
    });

    messageDiv.appendChild(messageContent);
    messageDiv.appendChild(messageAvatar);
    messageDiv.appendChild(messageTime);

    chatMessages.appendChild(messageDiv);

    this.scrollToBottom();
    this.animateMessage(messageDiv);
  }

  formatMessage(message) {
    return message
      .replace(/\n/g, "<br>")
      .replace(/\*\*(.*?)\*\*/g, "<strong>$1</strong>")
      .replace(/\*(.*?)\*/g, "<em>$1</em>")
      .replace(/`(.*?)`/g, "<code>$1</code>")
      .replace(/^• /gm, "• ")
      .replace(/^🌱|🐛|💰|🌤️|🚜|🏛️/gm, '<span class="emoji-icon">$&</span>');
  }

  showTypingIndicator() {
    const chatMessages = document.getElementById("chat-messages");
    if (!chatMessages) return;

    const typingDiv = document.createElement("div");
    typingDiv.className = "message bot typing-indicator";
    typingDiv.id = "typing-indicator";

    const messageContent = document.createElement("div");
    messageContent.className = "message-content";
    messageContent.innerHTML = `
            <div class="typing-animation">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <span class="typing-text">AI किसान सलाहकार सोच रहा है...</span>
        `;

    const messageAvatar = document.createElement("div");
    messageAvatar.className = "message-avatar";
    messageAvatar.innerHTML = '<i class="fas fa-robot pulse"></i>';

    typingDiv.appendChild(messageContent);
    typingDiv.appendChild(messageAvatar);

    chatMessages.appendChild(typingDiv);
    this.scrollToBottom();
  }

  removeTypingIndicator() {
    const typingIndicator = document.getElementById("typing-indicator");
    if (typingIndicator) {
      typingIndicator.style.opacity = "0";
      setTimeout(() => {
        if (typingIndicator.parentElement) {
          typingIndicator.remove();
        }
      }, 300);
    }
  }

  showQuickSuggestions() {
    const suggestions = [
      "मेरी फसल में कीड़े लगे हैं, क्या करूं?",
      "टमाटर का भाव क्या चल रहा है?",
      "बारिश के मौसम में कौन सी फसल लगाऊं?",
      "आलू की खेती कैसे करें?",
      "सरकारी योजना के बारे में बताएं",
    ];

    const randomSuggestion =
      suggestions[Math.floor(Math.random() * suggestions.length)];

    const suggestionDiv = document.createElement("div");
    suggestionDiv.className = "quick-suggestion";
    suggestionDiv.innerHTML = `
            <div class="suggestion-content">
                <p>💡 You might also ask:</p>
                <button onclick="farmingChatbot.sendQuickMessage('${randomSuggestion}')" class="suggestion-btn">
                    ${randomSuggestion}
                </button>
            </div>
        `;

    const chatMessages = document.getElementById("chat-messages");
    chatMessages.appendChild(suggestionDiv);
    this.scrollToBottom();

    // Auto remove after 10 seconds
    setTimeout(() => {
      if (suggestionDiv.parentElement) {
        suggestionDiv.remove();
      }
    }, 10000);
  }

  adjustInputHeight() {
    const input = document.getElementById("chat-input");
    if (input) {
      input.style.height = "auto";
      input.style.height = Math.min(input.scrollHeight, 120) + "px";
    }
  }

  scrollToBottom() {
    const chatMessages = document.getElementById("chat-messages");
    if (chatMessages) {
      chatMessages.scrollTo({
        top: chatMessages.scrollHeight,
        behavior: "smooth",
      });
    }
  }

  animateMessage(messageDiv) {
    messageDiv.style.opacity = "0";
    messageDiv.style.transform = "translateY(20px)";

    requestAnimationFrame(() => {
      messageDiv.style.transition = "all 0.3s ease";
      messageDiv.style.opacity = "1";
      messageDiv.style.transform = "translateY(0)";
    });
  }

  clearChat() {
    const chatMessages = document.getElementById("chat-messages");
    if (chatMessages) {
      chatMessages.innerHTML = "";
      this.addWelcomeMessage();
    }
  }
}

// Enhanced CSS for perfect chatbot styling
const enhancedChatbotStyles = `
<style>
.chatbot-welcome-prompt {
    position: fixed;
    bottom: 150px;
    right: 2rem;
    background: linear-gradient(135deg, #2E7D32, #4CAF50);
    color: white;
    padding: 1.5rem;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    z-index: 1001;
    transform: translateX(100%);
    opacity: 0;
    transition: all 0.5s ease;
    max-width: 300px;
}

.chatbot-welcome-prompt.show {
    transform: translateX(0);
    opacity: 1;
}

.welcome-prompt-content {
    text-align: center;
}

.welcome-prompt-content i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.btn-try-now {
    background: white;
    color: #2E7D32;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    margin-top: 1rem;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-try-now:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.btn-close-prompt {
    position: absolute;
    top: 10px;
    right: 10px;
    background: none;
    border: none;
    color: white;
    cursor: pointer;
    padding: 0.5rem;
}

.message {
    margin-bottom: 1.5rem;
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    position: relative;
    animation: messageSlideIn 0.5s ease;
}

@keyframes messageSlideIn {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.message.user {
    flex-direction: row-reverse;
}

.message-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
    position: relative;
}

.message.user .message-avatar {
    background: linear-gradient(135deg, #2E7D32, #4CAF50);
    color: white;
}

.message.bot .message-avatar {
    background: linear-gradient(135deg, #E8F5E8, #F1F8E9);
    color: #2E7D32;
    border: 2px solid #4CAF50;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

.message-content {
    max-width: 80%;
    padding: 1rem 1.25rem;
    border-radius: 20px;
    font-size: 0.95rem;
    line-height: 1.5;
    position: relative;
}

.message.user .message-content {
    background: linear-gradient(135deg, #2E7D32, #4CAF50);
    color: white;
    border-bottom-right-radius: 8px;
}

.message.bot .message-content {
    background: #FFFFFF;
    color: #2E2E2E;
    border: 1px solid #E0E0E0;
    border-bottom-left-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.emoji-icon {
    font-size: 1.2em;
    margin-right: 0.25em;
}

.message-time {
    position: absolute;
    bottom: -20px;
    font-size: 0.7rem;
    color: #999;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.message.user .message-time {
    right: 0;
}

.message.bot .message-time {
    left: 0;
}

.message:hover .message-time {
    opacity: 1;
}

.typing-animation {
    display: inline-flex;
    gap: 4px;
    margin-right: 10px;
}

.typing-animation span {
    width: 8px;
    height: 8px;
    background: #4CAF50;
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out;
}

.typing-animation span:nth-child(1) { animation-delay: -0.32s; }
.typing-animation span:nth-child(2) { animation-delay: -0.16s; }

@keyframes typing {
    0%, 80%, 100% {
        transform: scale(0.8);
        opacity: 0.5;
    }
    40% {
        transform: scale(1.2);
        opacity: 1;
    }
}

.typing-text {
    color: #666;
    font-size: 0.9rem;
    font-style: italic;
}

.quick-suggestion {
    margin: 1rem 0;
    text-align: center;
    animation: fadeInUp 0.5s ease;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.suggestion-content {
    background: #F0F8F0;
    border: 1px solid #C8E6C9;
    border-radius: 15px;
    padding: 1rem;
}

.suggestion-btn {
    background: white;
    border: 1px solid #4CAF50;
    color: #2E7D32;
    padding: 0.75rem 1.25rem;
    border-radius: 25px;
    margin-top: 0.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.suggestion-btn:hover {
    background: #4CAF50;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(76,175,80,0.3);
}

#chat-input {
    resize: none;
    overflow-y: auto;
    min-height: 45px;
    max-height: 120px;
}

.chat-messages::-webkit-scrollbar {
    width: 6px;
}

.chat-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.chat-messages::-webkit-scrollbar-thumb {
    background: #4CAF50;
    border-radius: 3px;
}

.chat-messages::-webkit-scrollbar-thumb:hover {
    background: #2E7D32;
}

code {
    background: #f5f5f5;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
}

.message.bot .message-content code {
    background: #E8F5E8;
}

.message.user .message-content code {
    background: rgba(255,255,255,0.2);
}

@media (max-width: 768px) {
    .chatbot-welcome-prompt {
        right: 1rem;
        left: 1rem;
        max-width: none;
    }
}
</style>
`;

// Inject enhanced styles
if (!document.getElementById("enhanced-chatbot-styles")) {
  const styleElement = document.createElement("div");
  styleElement.id = "enhanced-chatbot-styles";
  styleElement.innerHTML = enhancedChatbotStyles;
  document.head.appendChild(styleElement);
}

// Initialize the professional chatbot
const farmingChatbot = new ProfessionalFarmingChatbot();

// Global functions for HTML onclick events
function toggleChatbot() {
  farmingChatbot.toggleChatbot();
}

function sendMessage() {
  farmingChatbot.sendMessage();
}

function sendQuickMessage(message) {
  farmingChatbot.sendQuickMessage(message);
}
