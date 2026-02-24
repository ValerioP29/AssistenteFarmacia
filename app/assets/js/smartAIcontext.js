// SMART CHATBOT
const SmartAIContext = {
  key: "smart_ai_context",

  saveContext(type, data = {}) {
    const context = {
      type,
      data,
      ts: Date.now(),
    };
    sessionStorage.setItem(this.key, JSON.stringify(context));
  },

  getContext() {
    try {
      const raw = sessionStorage.getItem(this.key);
      return raw ? JSON.parse(raw) : null;
    } catch {
      return null;
    }
  },

  clearContext() {
    sessionStorage.removeItem(this.key);
  },

  startChat(type, data = {}) {
    this.saveContext(type, data);
    this.showLoader("Apertura assistente...");
    setTimeout(() => {
      window.location.href = AppURLs.page.chatbot();
    }, 1000);
  },

  showLoader(msg = "Caricamento...") {
    const overlay = document.createElement("div");
    overlay.className = "chat-loader";
    overlay.innerHTML = `
		<div class="chat-loader-inner">
			<div class="chat-spinner"></div>
			<p>${msg}</p>
		</div>`;
    document.body.appendChild(overlay);
    setTimeout(() => overlay.classList.add("visible"), 10);

    window.addEventListener("pageshow", (event) => {
      if (event.persisted) {
        const old = document.querySelector(".chat-loader");
        if (old) {
          old.remove();
        }
      }
    });
  },

  injectContextIfPresent() {
    const ctx = this.getContext();
    if (!ctx) return false;

    let message = "";
    switch (ctx.type) {
      case "servizio":
        message = `Vorrei informazioni sul servizio "${ctx.data.nome}".`;
        break;
      case "evento":
        message = `Vorrei sapere di più sull'evento "${ctx.data.nome}" del ${
          ctx.data.data || "prossimo periodo"
        }.`;
        break;
      case "prenotazione":
        message = `Vorrei chiedere un chiarimento sulla prenotazione "${ctx.data.nome}".`;
        break;
      case "promozione":
        message = `Voglio scoprire di più sulla promozione "${ctx.data.nome}".`;
        break;
      default:
        message = ctx.data?.prompt || "Ho una domanda per la farmacia.";
    }

    appendMessage(`<p>${message}</p>`, "user");

    fetchBotSend({
      message: message,
    });

    setTimeout(() => {
      this.clearContext();
    }, 800);

    return true;
  },
};
