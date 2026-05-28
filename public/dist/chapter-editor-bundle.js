(() => {
  // src/js/i18n.js
  var LANG_KEY = "app_lang";
  var translations = {};
  var enTranslations = {};
  var appLanguages = {
    en: "English",
    tr: "T\xFCrk\xE7e",
    no: "Norsk"
    // 'zh-TW': '繁體中文'
  };
  async function loadLanguageFile(lang) {
    try {
      const langData = await window.api.getLangFile(lang);
      return JSON.parse(langData);
    } catch (error) {
      console.error(`Could not load language file for: ${lang}`, error);
      return null;
    }
  }
  function getNested(obj, path) {
    return path.split(".").reduce((acc, part) => acc && acc[part], obj);
  }
  function t(key, substitutions = {}) {
    let result = getNested(translations, key);
    let isFallback = false;
    if (result === void 0) {
      result = getNested(enTranslations, key);
      isFallback = true;
    }
    if (result === void 0) {
      return key;
    }
    if (typeof result === "string") {
      for (const [subKey, subValue] of Object.entries(substitutions)) {
        result = result.replace(`{${subKey}}`, subValue);
      }
      if (isFallback && (localStorage.getItem(LANG_KEY) || "en") !== "en") {
        result += "*";
      }
    }
    return result;
  }
  function translateElement(element) {
    const key = element.dataset.i18n;
    if (key) {
      if (element.children.length === 0 || element.tagName.toLowerCase() === "title") {
        element.textContent = t(key);
      } else {
        for (const node of element.childNodes) {
          if (node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0) {
            node.textContent = ` ${t(key)} `;
            break;
          }
        }
      }
    }
    if (element.dataset.i18nTitle) {
      element.title = t(element.dataset.i18nTitle);
    }
    if (element.dataset.i18nPlaceholder) {
      element.placeholder = t(element.dataset.i18nPlaceholder);
    }
  }
  function applyTranslationsTo(rootElement) {
    if (!rootElement) return;
    if (rootElement.matches("[data-i18n], [data-i18n-title], [data-i18n-placeholder]")) {
      translateElement(rootElement);
    }
    rootElement.querySelectorAll("[data-i18n], [data-i18n-title], [data-i18n-placeholder]").forEach(translateElement);
  }
  function applyTranslations() {
    applyTranslationsTo(document.body);
    document.documentElement.lang = localStorage.getItem(LANG_KEY) || "en";
  }
  function populateLanguageSwitcher() {
    const menus = document.querySelectorAll("#js-lang-switcher-menu");
    if (menus.length === 0) return;
    const currentLang = localStorage.getItem(LANG_KEY) || "en";
    menus.forEach((menu) => {
      menu.innerHTML = "";
      for (const [code, name] of Object.entries(appLanguages)) {
        const li = document.createElement("li");
        const a = document.createElement("a");
        a.href = "#";
        a.dataset.lang = code;
        a.textContent = name;
        if (code === currentLang) {
          a.classList.add("active");
        }
        a.addEventListener("click", (e) => {
          e.preventDefault();
          if (code !== currentLang) {
            setLanguage(code);
          }
        });
        li.appendChild(a);
        menu.appendChild(li);
      }
    });
  }
  function setLanguage(lang) {
    localStorage.setItem(LANG_KEY, lang);
    window.location.reload();
  }
  async function initI18n(isDashboard = false) {
    const lang = localStorage.getItem(LANG_KEY) || "en";
    enTranslations = await loadLanguageFile("en") || {};
    if (lang !== "en") {
      translations = await loadLanguageFile(lang) || {};
    } else {
      translations = enTranslations;
    }
    localStorage.setItem(LANG_KEY, lang);
    applyTranslations();
    populateLanguageSwitcher();
  }

  // src/utils/html-processing.js
  function htmlToPlainText(html) {
    if (!html) return "";
    let s = html.replace(/<br\s*\/?>/gi, "\n");
    const block = "(?:p|div|section|article|header|footer|nav|aside|h[1-6]|ul|ol|li|table|thead|tbody|tfoot|tr|th|td|blockquote|pre|hr)";
    s = s.replace(new RegExp(`<\\s*${block}[^>]*>`, "gi"), "\n").replace(new RegExp(`<\\/\\s*${block}\\s*>`, "gi"), "\n");
    s = s.replace(/<[^>]+>/g, "");
    s = s.replace(/\s+([.,!?;:])/g, "$1").replace(/(\() +/g, "$1").replace(/ +(\))/g, "$1");
    s = s.replace(/[ \t]+\n/g, "\n").replace(/\n[ \t]+/g, "\n").replace(/\n{3,}/g, "\n\n").replace(/[ \t]{2,}/g, " ");
    return s.trim();
  }
  function processSourceContentForMarkers(htmlString) {
    if (!htmlString) {
      return htmlString;
    }
    const markerRegex = /(\[\[#(\d+)\]\])|(\{\{#(\d+)\}\})/g;
    return htmlString.replace(markerRegex, (match, p1, p2, p3, p4) => {
      const number = p2 || p4;
      const type = p1 ? "opening" : "closing";
      return `<a href="#" class="translation-marker-link" data-marker-id="${number}" data-marker-type="${type}">${match}</a>`;
    });
  }

  // src/js/prompt-editors/rephrase-editor.js
  var debounce = (func, delay) => {
    let timeout;
    return function(...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), delay);
    };
  };
  var defaultState = {
    // Default state for the rephrase editor form
    instructions: "",
    tense: "past"
  };
  var buildSurroundingTextBlock = (wordsBefore, wordsAfter) => {
    if (!wordsBefore && !wordsAfter) {
      return "";
    }
    if (wordsBefore && wordsAfter) {
      return t("prompt.rephrase.user.surroundingTextBlock", { wordsBefore, wordsAfter });
    }
    if (wordsBefore) {
      return t("prompt.rephrase.user.surroundingTextBlockBeforeOnly", { wordsBefore });
    }
    return t("prompt.rephrase.user.surroundingTextBlockAfterOnly", { wordsAfter });
  };
  var buildPromptJson = (formData, context, userDictionary = "") => {
    const { selectedText, wordCount, languageForPrompt, wordsBefore, wordsAfter } = context;
    const system = t("prompt.rephrase.system.base", {
      instructions: formData.instructions,
      tense: formData.tense,
      dictionary: userDictionary,
      language: languageForPrompt || "English"
    });
    const truncatedText = selectedText.length > 4096 ? selectedText.substring(0, 4096) + "..." : selectedText;
    const surroundingText = buildSurroundingTextBlock(wordsBefore, wordsAfter);
    const userParts = [];
    if (surroundingText) {
      userParts.push(surroundingText);
    }
    userParts.push(t("prompt.rephrase.user.textToRewrite", {
      wordCount,
      text: wordCount > 0 ? truncatedText : "{message}"
    }));
    const user = userParts.filter(Boolean).join("\n\n");
    return {
      system: system.replace(/\n\n\n/g, "\n\n"),
      user,
      ai: ""
    };
  };
  var updatePreview = async (container, context) => {
    const form = container.querySelector("#rephrase-editor-form");
    if (!form) return;
    const formData = {
      instructions: form.elements.instructions.value.trim(),
      tense: form.elements.tense.value
    };
    const systemPreview = container.querySelector(".js-preview-system");
    const userPreview = container.querySelector(".js-preview-user");
    const aiPreview = container.querySelector(".js-preview-ai");
    if (!systemPreview || !userPreview || !aiPreview) return;
    let dictionaryContextualContent = await window.api.getDictionaryContentForAI(context.bookId, "translation");
    const previewContext = { ...context };
    try {
      const promptJson = buildPromptJson(formData, previewContext, dictionaryContextualContent);
      systemPreview.textContent = promptJson.system;
      userPreview.textContent = promptJson.user;
      aiPreview.textContent = promptJson.ai || t("prompt.preview.empty");
    } catch (error) {
      systemPreview.textContent = `Error building preview: ${error.message}`;
      userPreview.textContent = "";
      aiPreview.textContent = "";
    }
  };
  var populateForm = (container, state, bookId) => {
    const form = container.querySelector("#rephrase-editor-form");
    if (!form) return;
    const storageKey = `tense-preference-${bookId}-rephrase`;
    const savedTense = localStorage.getItem(storageKey);
    const tense = state.tense || savedTense || defaultState.tense;
    form.elements.instructions.value = state.instructions || "";
    form.elements.tense.value = tense;
    const tenseButtons = form.querySelectorAll(".js-tense-btn");
    tenseButtons.forEach((btn) => {
      btn.classList.toggle("btn-active", btn.dataset.tense === tense);
    });
  };
  var init = async (container, context) => {
    try {
      const templateHtml = await window.api.getTemplate("prompt/rephrase-editor");
      container.innerHTML = templateHtml;
      applyTranslationsTo(container);
      const wordCount = context.selectedText ? context.selectedText.trim().split(/\s+/).filter(Boolean).length : 0;
      const fullContext = { ...context, wordCount };
      populateForm(container, context.initialState || defaultState, context.bookId);
      const form = container.querySelector("#rephrase-editor-form");
      const debouncedUpdatePreview = debounce(() => {
        updatePreview(container, fullContext);
      }, 500);
      if (form) {
        form.addEventListener("input", () => {
          debouncedUpdatePreview();
        });
        const tenseGroup = form.querySelector(".js-tense-group");
        if (tenseGroup) {
          tenseGroup.addEventListener("click", (e) => {
            const button = e.target.closest(".js-tense-btn");
            if (!button) return;
            const newTense = button.dataset.tense;
            tenseGroup.querySelectorAll(".js-tense-btn").forEach((btn) => btn.classList.remove("btn-active"));
            button.classList.add("btn-active");
            form.elements.tense.value = newTense;
            const storageKey = `tense-preference-${context.bookId}-rephrase`;
            localStorage.setItem(storageKey, newTense);
            debouncedUpdatePreview();
          });
        }
      }
      await updatePreview(container, fullContext);
    } catch (error) {
      container.innerHTML = `<p class="p-4 text-error">${t("prompt.errorLoadForm")}</p>`;
      console.error(error);
    }
  };

  // src/js/prompt-editors/translate-editor.js
  var debounce2 = (func, delay) => {
    let timeout;
    return function(...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), delay);
    };
  };
  var defaultState2 = {
    // Default state for the translate editor form
    instructions: "",
    tense: "past",
    contextPairs: 4,
    translationMemoryIds: []
  };
  var buildTranslationContextBlock = (translationPairs, languageForPrompt, targetLanguage) => {
    if (!translationPairs || translationPairs.length === 0) {
      return [];
    }
    const contextMessages = [];
    translationPairs.forEach((pair) => {
      const sourceText = htmlToPlainText(pair.source || "");
      const targetText = htmlToPlainText(pair.target || "");
      if (sourceText && targetText) {
        contextMessages.push({
          role: "user",
          content: t("prompt.translate.user.textToTranslate", {
            sourceLanguage: languageForPrompt,
            targetLanguage,
            text: sourceText
          })
        });
        contextMessages.push({
          role: "assistant",
          content: targetText
        });
      }
    });
    return contextMessages;
  };
  var buildPromptJson2 = (formData, context, userDictionary = "") => {
    const { selectedText, languageForPrompt, targetLanguage, translationPairs } = context;
    const plainTextToTranslate = selectedText;
    const system = t("prompt.translate.system.base", {
      sourceLanguage: languageForPrompt,
      targetLanguage,
      instructions: formData.instructions,
      tense: formData.tense,
      dictionary: userDictionary
    }).trim();
    const contextMessages = buildTranslationContextBlock(translationPairs, languageForPrompt, targetLanguage);
    const finalUserPromptParts = [];
    finalUserPromptParts.push(t("prompt.translate.user.textToTranslate", {
      sourceLanguage: languageForPrompt,
      targetLanguage,
      text: plainTextToTranslate
    }));
    const finalUserPrompt = finalUserPromptParts.filter(Boolean).join("\n\n");
    return {
      system,
      context_pairs: contextMessages,
      user: finalUserPrompt,
      ai: ""
    };
  };
  var updatePreview2 = async (container, context) => {
    const form = container.querySelector("#translate-editor-form");
    if (!form) {
      return;
    }
    const formData = {
      instructions: form.elements.instructions.value.trim(),
      tense: form.elements.tense.value,
      contextPairs: parseInt(form.elements.context_pairs.value, 10) || 0
    };
    const systemPreview = container.querySelector(".js-preview-system");
    const userPreview = container.querySelector(".js-preview-user");
    const aiPreview = container.querySelector(".js-preview-ai");
    const contextPairsContainer = container.querySelector(".js-preview-context-pairs");
    if (!systemPreview || !userPreview || !aiPreview || !contextPairsContainer) {
      return;
    }
    const previewContext = { ...context, translationPairs: [] };
    if (formData.contextPairs > 0 && context.chapterId) {
      try {
        const pairs = await window.api.getTranslationContext({
          chapterId: context.chapterId,
          pairCount: formData.contextPairs,
          selectedText: context.selectedText
        });
        previewContext.translationPairs = pairs;
      } catch (error) {
        console.error("Failed to fetch translation context for preview:", error);
        userPreview.textContent = `Error fetching context: ${error.message}`;
        return;
      }
    }
    let userDictionaryContent = await window.api.getDictionaryContentForAI(context.bookId, "translation");
    try {
      const promptJson = buildPromptJson2(formData, previewContext, userDictionaryContent);
      systemPreview.textContent = promptJson.system;
      userPreview.textContent = promptJson.user;
      aiPreview.textContent = promptJson.ai || t("prompt.preview.empty");
      contextPairsContainer.innerHTML = "";
      if (promptJson.context_pairs && promptJson.context_pairs.length > 0) {
        promptJson.context_pairs.forEach((message, index) => {
          const pairNumber = Math.floor(index / 2) + 1;
          const roleTitle = message.role === "user" ? t("prompt.preview.contextUser", { number: pairNumber }) : t("prompt.preview.contextAssistant", { number: pairNumber });
          const title = document.createElement("h3");
          title.className = "text-lg font-semibold mt-4 font-mono";
          title.textContent = roleTitle;
          title.classList.add(message.role === "user" ? "text-info" : "text-accent");
          const pre = document.createElement("pre");
          pre.className = "bg-base-200 p-4 rounded-md text-xs whitespace-pre-wrap font-mono";
          const code = document.createElement("code");
          code.textContent = message.content;
          pre.appendChild(code);
          contextPairsContainer.appendChild(title);
          contextPairsContainer.appendChild(pre);
        });
      }
    } catch (error) {
      systemPreview.textContent = `Error building preview: ${error.message}`;
      userPreview.textContent = "";
      aiPreview.textContent = "";
      contextPairsContainer.innerHTML = "";
    }
  };
  var populateForm2 = (container, state, bookId) => {
    const form = container.querySelector("#translate-editor-form");
    if (!form) {
      return;
    }
    const storageKey = `tense-preference-${bookId}-translate`;
    const savedTense = localStorage.getItem(storageKey);
    const tense = state.tense || savedTense || defaultState2.tense;
    form.elements.instructions.value = state.instructions || "";
    form.elements.context_pairs.value = state.contextPairs !== void 0 ? state.contextPairs : 4;
    form.elements.tense.value = tense;
    const tenseButtons = form.querySelectorAll(".js-tense-btn");
    tenseButtons.forEach((btn) => {
      btn.classList.toggle("btn-active", btn.dataset.tense === tense);
    });
  };
  var init2 = async (container, context) => {
    try {
      const templateHtml = await window.api.getTemplate("prompt/translate-editor");
      container.innerHTML = templateHtml;
      applyTranslationsTo(container);
      const fullContext = { ...context };
      populateForm2(container, context.initialState || defaultState2, context.bookId);
      const form = container.querySelector("#translate-editor-form");
      const debouncedUpdatePreview = debounce2(() => {
        updatePreview2(container, fullContext);
      }, 500);
      if (form) {
        form.addEventListener("input", debouncedUpdatePreview);
        form.addEventListener("change", (e) => {
          if (e.target.type === "checkbox") {
            debouncedUpdatePreview();
          }
        });
        const tenseGroup = form.querySelector(".js-tense-group");
        if (tenseGroup) {
          tenseGroup.addEventListener("click", (e) => {
            const button = e.target.closest(".js-tense-btn");
            if (!button) {
              return;
            }
            const newTense = button.dataset.tense;
            tenseGroup.querySelectorAll(".js-tense-btn").forEach((btn) => btn.classList.remove("btn-active"));
            button.classList.add("btn-active");
            form.elements.tense.value = newTense;
            const storageKey = `tense-preference-${context.bookId}-translate`;
            localStorage.setItem(storageKey, newTense);
            debouncedUpdatePreview();
          });
        }
      }
      await updatePreview2(container, fullContext);
    } catch (error) {
      container.innerHTML = `<p class="p-4 text-error">${t("prompt.errorLoadForm")}</p>`;
      console.error(error);
    }
  };

  // src/js/prompt-editor.js
  var AI_SETTINGS_KEYS = {
    MODEL: "parallel-leaves-ai-model",
    TEMPERATURE: "parallel-leaves-ai-temperature"
  };
  var editors = {
    "rephrase": { init },
    "translate": { init: init2 }
  };
  var promptBuilders = {
    "rephrase": buildPromptJson,
    "translate": buildPromptJson2
  };
  var formDataExtractors = {
    "rephrase": (form) => ({
      instructions: form.elements.instructions.value.trim(),
      tense: form.elements.tense.value
    }),
    // MODIFIED: The extractor for 'translate' no longer needs to get selected TM IDs.
    "translate": (form) => {
      return {
        instructions: form.elements.instructions.value.trim(),
        tense: form.elements.tense.value,
        contextPairs: parseInt(form.elements.context_pairs.value, 10) || 0
      };
    }
  };
  var modalEl;
  var currentContext;
  var currentEditorInterface;
  var isAiActionActive = false;
  var originalFragmentJson = null;
  var aiActionRange = null;
  var floatingToolbar = null;
  var currentAiParams = null;
  var currentPromptId = null;
  var currentActionMarkers = null;
  function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }
  function showAiSpinner() {
    const overlay = document.getElementById("ai-action-spinner-overlay");
    if (overlay) {
      overlay.classList.remove("hidden");
    }
  }
  function hideAiSpinner() {
    const overlay = document.getElementById("ai-action-spinner-overlay");
    if (overlay) {
      overlay.classList.add("hidden");
    }
  }
  var loadPrompt = async (promptId) => {
    if (!modalEl) {
      return;
    }
    const toggleBtn = modalEl.querySelector(".js-toggle-preview-btn");
    if (toggleBtn) {
      toggleBtn.textContent = t("editor.showPreview");
    }
    const placeholder = modalEl.querySelector(".js-prompt-placeholder");
    const customEditorPane = modalEl.querySelector(".js-custom-editor-pane");
    const customPromptTitle = customEditorPane.querySelector(".js-custom-prompt-title");
    const customFormContainer = customEditorPane.querySelector(".js-custom-form-container");
    const editorConfig = editors[promptId];
    if (!editorConfig) {
      console.error(`No editor configured for promptId: ${promptId}`);
      placeholder.classList.remove("hidden");
      customEditorPane.classList.add("hidden");
      placeholder.innerHTML = `<p class="text-error">${t("prompt.errorNoEditorForPrompt", { promptId })}</p>`;
      return;
    }
    placeholder.classList.add("hidden");
    customEditorPane.classList.remove("hidden");
    customPromptTitle.textContent = t(`prompt.${promptId}.title`);
    customFormContainer.innerHTML = `<div class="p-4 text-center"><span class="loading loading-spinner"></span></div>`;
    await editorConfig.init(customFormContainer, currentContext);
  };
  async function cleanupAiAction() {
    if (floatingToolbar) {
      floatingToolbar.remove();
      floatingToolbar = null;
    }
    if (currentEditorInterface) {
      await currentEditorInterface.setEditable(true);
      await currentEditorInterface.cleanupSuggestion();
    }
    isAiActionActive = false;
    originalFragmentJson = null;
    aiActionRange = null;
    currentAiParams = null;
    currentActionMarkers = null;
    if (currentEditorInterface.type === "iframe") {
      updateToolbarState(null);
    }
  }
  async function handleFloatyApply() {
    if (!isAiActionActive || !currentEditorInterface) {
      return;
    }
    if (currentPromptId === "translate" && currentAiParams && currentAiParams.logData) {
      window.api.logTranslationEvent(currentAiParams.logData).catch((err) => console.error("Failed to log translation event on apply:", err));
    }
    await cleanupAiAction();
  }
  async function handleFloatyDiscard() {
    if (!isAiActionActive || !currentEditorInterface || !originalFragmentJson) {
      return;
    }
    if (currentActionMarkers && currentActionMarkers.opening) {
      try {
        const chapterId = currentContext.chapterId;
        const sourceContainer = document.querySelector(`#source-chapter-scroll-target-${chapterId} .source-content-readonly`);
        if (sourceContainer) {
          let sourceHtml = sourceContainer.innerHTML;
          const openingMarkerPattern = `(<a[^>]*>\\s*)?${escapeRegex(currentActionMarkers.opening)}(\\s*<\\/a>)?\\s*`;
          const closingMarkerPattern = `\\s*(<a[^>]*>\\s*)?${escapeRegex(currentActionMarkers.closing)}(\\s*<\\/a>)?`;
          const openingRegex = new RegExp(openingMarkerPattern, "g");
          const closingRegex = new RegExp(closingMarkerPattern, "g");
          sourceHtml = sourceHtml.replace(openingRegex, "").replace(closingRegex, "");
          await window.api.updateChapterField({
            chapterId,
            field: "source_content",
            value: sourceHtml
          });
          sourceContainer.innerHTML = processSourceContentForMarkers(sourceHtml);
        }
      } catch (error) {
        console.error("Failed to remove translation markers from source on discard:", error);
      }
    }
    await currentEditorInterface.discardAiSuggestion(aiActionRange.from, aiActionRange.to, originalFragmentJson);
    await cleanupAiAction();
  }
  async function handleFloatyRetry() {
    if (!isAiActionActive || !currentEditorInterface || !currentAiParams) {
      return;
    }
    const actionToRetry = currentAiParams.action;
    const contextForRetry = currentAiParams.context;
    const previousFormData = currentAiParams.formData;
    if (floatingToolbar) {
      floatingToolbar.remove();
      floatingToolbar = null;
    }
    await currentEditorInterface.discardAiSuggestion(aiActionRange.from, aiActionRange.to, originalFragmentJson);
    await currentEditorInterface.setEditable(true);
    await currentEditorInterface.cleanupSuggestion();
    isAiActionActive = false;
    originalFragmentJson = null;
    aiActionRange = null;
    if (currentEditorInterface.type === "iframe") {
      updateToolbarState(null);
    }
    openPromptEditor(contextForRetry, actionToRetry, previousFormData);
  }
  function createFloatingToolbar(from, to, model) {
    if (floatingToolbar) {
      floatingToolbar.remove();
    }
    const modelName = model.split("/").pop() || model;
    const toolbarEl = document.createElement("div");
    toolbarEl.id = "ai-floating-toolbar";
    toolbarEl.innerHTML = `
        <button data-action="apply" data-i18n-title="editor.aiToolbar.applyTitle"><i class="bi bi-check-lg"></i> <span data-i18n="editor.aiToolbar.apply">Apply</span></button>
        <button data-action="retry" data-i18n-title="editor.aiToolbar.retryTitle"><i class="bi bi-arrow-repeat"></i> <span data-i18n="editor.aiToolbar.retry">Retry</span></button>
        <button data-action="discard" data-i18n-title="editor.aiToolbar.discardTitle"><i class="bi bi-x-lg"></i> <span data-i18n="editor.aiToolbar.discard">Discard</span></button>
        <div class="divider-vertical"></div>
        <span class="text-gray-400">${modelName}</span>
    `;
    document.body.appendChild(toolbarEl);
    floatingToolbar = toolbarEl;
    applyTranslationsTo(toolbarEl);
    toolbarEl.style.left = `40%`;
    toolbarEl.style.top = `20%`;
    toolbarEl.addEventListener("mousedown", (e) => e.preventDefault());
    toolbarEl.addEventListener("click", (e) => {
      const button = e.target.closest("button");
      if (!button) {
        return;
      }
      const action = button.dataset.action;
      if (action === "apply") {
        handleFloatyApply();
      }
      if (action === "discard") {
        handleFloatyDiscard();
      }
      if (action === "retry") {
        handleFloatyRetry();
      }
    });
  }
  async function startAiAction(params) {
    currentAiParams = params;
    isAiActionActive = true;
    if (currentEditorInterface.type === "iframe") {
      updateToolbarState(null);
    }
    await currentEditorInterface.setEditable(false);
    showAiSpinner();
    try {
      console.log("Sending prompt to AI:", params.prompt);
      const result = await window.api.processLLMText({
        prompt: params.prompt,
        model: params.model,
        temperature: params.temperature,
        translation_memory_ids: params.translation_memory_ids,
        bookId: params.bookId
      });
      hideAiSpinner();
      if (result.success && result.data.choices && result.data.choices.length > 0) {
        let newContentText = result.data.choices[0].message.content ?? "No content generated.";
        newContentText = newContentText.trim();
        if (currentPromptId === "translate") {
          const context = currentAiParams.context;
          currentAiParams.logData = {
            bookId: context.bookId,
            chapterId: context.chapterId,
            sourceText: context.selectedText,
            targetText: newContentText,
            marker: params.openingMarker,
            model: params.model,
            temperature: params.temperature
          };
        }
        let newContentHtml;
        const textWithMarkers = params.openingMarker && params.closingMarker ? `${params.openingMarker} ${newContentText} ${params.closingMarker}` : newContentText;
        const isInlineSelection = originalFragmentJson && originalFragmentJson.length > 0 && !["paragraph", "heading", "blockquote", "list_item", "ordered_list", "bullet_list", "horizontal_rule", "code_block"].includes(originalFragmentJson[0].type);
        if (isInlineSelection) {
          newContentHtml = textWithMarkers.replace(/\n/g, "<br>");
        } else {
          newContentHtml = "<p>" + textWithMarkers.replace(/\n\n/g, "</p><p>").replace(/\n/g, "<br>") + "</p>";
        }
        const replacementData = await currentEditorInterface.replaceRangeWithSuggestion(
          aiActionRange.from,
          aiActionRange.to,
          newContentHtml
        );
        if (replacementData) {
          aiActionRange.to = replacementData.finalRange.to;
          createFloatingToolbar(aiActionRange.from, aiActionRange.to, params.model);
          if (replacementData.finalRange) {
            setTimeout(() => {
              const iframeEl = currentContext.activeEditorView.frameElement;
              const container = document.getElementById("js-target-column-container");
              const endCoords = replacementData.endCoords;
              if (iframeEl && container && endCoords) {
                const iframeRect = iframeEl.getBoundingClientRect();
                const containerRect = container.getBoundingClientRect();
                const contentEndAbsoluteY = iframeRect.top + endCoords.bottom;
                const contentEndRelativeY = contentEndAbsoluteY - containerRect.top;
                const desiredScrollTop = container.scrollTop + contentEndRelativeY - container.clientHeight + 50;
                if (desiredScrollTop > container.scrollTop) {
                  container.scrollTo({ top: desiredScrollTop, behavior: "smooth" });
                }
              }
            }, 100);
          }
        } else {
          console.error("Editor did not return a final range after replacement.");
          await handleFloatyDiscard();
        }
      } else {
        const errorMessage = result.error || (result.data.error ? result.data.error.message : "Unknown AI error.");
        throw new Error(errorMessage);
      }
    } catch (error) {
      console.error("AI Action Error:", error);
      window.showAlert(error.message);
      hideAiSpinner();
      await handleFloatyDiscard();
    }
  }
  async function populateModelDropdown() {
    if (!modalEl) {
      return;
    }
    const select = modalEl.querySelector(".js-llm-model-select");
    if (!select) {
      return;
    }
    try {
      const result = await window.api.getModels();
      if (!result.success || !result.models || result.models.length === 0) {
        throw new Error(result.message || "No models returned from API.");
      }
      const modelGroups = result.models;
      const popularDefaultModel = "openai/gpt-5.4";
      select.innerHTML = "";
      modelGroups.forEach((group) => {
        const optgroup = document.createElement("optgroup");
        optgroup.label = group.group;
        group.models.forEach((model) => {
          const option = new Option(model.name, model.id);
          optgroup.appendChild(option);
        });
        select.appendChild(optgroup);
      });
      const lastUsedModel = localStorage.getItem(AI_SETTINGS_KEYS.MODEL);
      const allModels = modelGroups.flatMap((g) => g.models);
      if (lastUsedModel && allModels.some((m) => m.id === lastUsedModel)) {
        select.value = lastUsedModel;
      } else if (allModels.some((m) => m.id === popularDefaultModel)) {
        select.value = popularDefaultModel;
      } else if (allModels.length > 0) {
        select.value = allModels[0].id;
      }
      localStorage.setItem(AI_SETTINGS_KEYS.MODEL, select.value);
    } catch (error) {
      console.error("Failed to populate AI model dropdowns:", error);
      select.innerHTML = '<option value="" disabled selected>Error loading</option>';
    }
  }
  async function handleModalApply() {
    if (!modalEl || isAiActionActive) {
      return;
    }
    const model = modalEl.querySelector(".js-llm-model-select").value;
    const temperature = parseFloat(modalEl.querySelector(".js-ai-temperature-slider").value);
    const action = currentPromptId;
    const form = modalEl.querySelector(".js-custom-editor-pane form");
    if (!model || !action || !form) {
      window.showAlert(t("prompt.errorApplyAction"));
      return;
    }
    const builder = promptBuilders[action];
    const extractor = formDataExtractors[action];
    if (!builder || !extractor) {
      window.showAlert(t("prompt.errorNoBuilder", { action }));
      return;
    }
    modalEl.close();
    currentEditorInterface = currentContext.editorInterface;
    if (!currentEditorInterface) {
      window.showAlert(t("prompt.errorNoActiveEditor"));
      return;
    }
    const formDataObj = extractor(form);
    const bookId = document.body.dataset.bookId;
    if (bookId) {
      const settingsToSave = { ...formDataObj };
      window.api.updatePromptSettings({ bookId, promptType: action, settings: settingsToSave }).catch((err) => console.error("Failed to save prompt settings:", err));
    }
    let selectionInfo;
    if (action === "translate") {
      if (!currentContext.insertionPoint) {
        window.showAlert(t("prompt.errorNoInsertionPoint"));
        return;
      }
      selectionInfo = {
        from: currentContext.insertionPoint.from,
        to: currentContext.insertionPoint.to,
        originalFragmentJson: [],
        selectedText: currentContext.selectedText
      };
    } else {
      selectionInfo = await currentEditorInterface.getSelectionInfo(action);
      if (!selectionInfo) {
        window.showAlert(t("prompt.errorNoSelection"));
        return;
      }
    }
    aiActionRange = { from: selectionInfo.from, to: selectionInfo.to };
    originalFragmentJson = selectionInfo.originalFragmentJson;
    const wordCount = selectionInfo.selectedText ? selectionInfo.selectedText.trim().split(/\s+/).filter(Boolean).length : 0;
    const promptContext = {
      ...currentContext,
      selectedText: selectionInfo.selectedText,
      wordCount,
      wordsBefore: selectionInfo.wordsBefore,
      wordsAfter: selectionInfo.wordsAfter
    };
    if (action === "translate" && formDataObj.contextPairs > 0) {
      try {
        const chapterId = currentContext.chapterId;
        const pairs = await window.api.getTranslationContext({
          chapterId,
          pairCount: formDataObj.contextPairs,
          selectedText: selectionInfo.selectedText
        });
        promptContext.translationPairs = pairs;
      } catch (error) {
        console.error("Failed to fetch translation context:", error);
        window.showAlert(t("prompt.errorFetchContext", { message: error.message }));
      }
    }
    let dictionaryContextualContent = await window.api.getDictionaryContentForAI(bookId, "translation");
    const prompt = builder(formDataObj, promptContext, dictionaryContextualContent);
    let openingMarker = "";
    let closingMarker = "";
    if (action === "translate") {
      if (currentActionMarkers) {
        openingMarker = currentActionMarkers.opening;
        closingMarker = currentActionMarkers.closing;
      } else {
        const allContentResult = await window.api.getAllBookContent(bookId);
        let highestNum = 0;
        if (allContentResult.success) {
          highestNum = await window.api.findHighestMarkerNumber(allContentResult.combinedHtml, "");
        } else {
          console.error("Could not fetch all book content for marker generation:", allContentResult.message);
          window.showAlert("Could not generate a translation marker. The translation will proceed without it.");
        }
        const newMarkerNum = highestNum + 1;
        openingMarker = `[[#${newMarkerNum}]]`;
        closingMarker = `{{#${newMarkerNum}}}`;
        currentActionMarkers = { opening: openingMarker, closing: closingMarker };
        try {
          const chapterId = currentContext.chapterId;
          const sourceContainer = document.querySelector(`#source-chapter-scroll-target-${chapterId} .source-content-readonly`);
          const range = currentContext.sourceSelectionRange;
          const openingMarkerNode = document.createTextNode(openingMarker + " ");
          const closingMarkerNode = document.createTextNode(" " + closingMarker);
          const endRange = range.cloneRange();
          endRange.collapse(false);
          endRange.insertNode(closingMarkerNode);
          range.collapse(true);
          range.insertNode(openingMarkerNode);
          const updatedHtmlContent = sourceContainer.innerHTML;
          await window.api.updateChapterField({
            chapterId,
            field: "source_content",
            value: updatedHtmlContent
          });
          const processedHtml = processSourceContentForMarkers(updatedHtmlContent);
          sourceContainer.innerHTML = processedHtml;
        } catch (e) {
          console.error("Could not insert markers into source text:", e);
          openingMarker = "";
          closingMarker = "";
          currentActionMarkers = null;
        }
      }
    }
    const aiParams = {
      prompt,
      model,
      temperature,
      action,
      context: promptContext,
      formData: formDataObj,
      openingMarker,
      closingMarker,
      // MODIFIED: translation_memory_ids is no longer sent. The server handles this automatically.
      bookId
    };
    startAiAction(aiParams);
  }
  function setupPromptEditor() {
    modalEl = document.getElementById("prompt-editor-modal");
    if (!modalEl) {
      return;
    }
    const applyBtn = modalEl.querySelector(".js-prompt-apply-btn");
    if (applyBtn) {
      applyBtn.addEventListener("click", handleModalApply);
    }
    const toggleBtn = modalEl.querySelector(".js-toggle-preview-btn");
    if (toggleBtn) {
      toggleBtn.addEventListener("click", () => {
        const formContainer = modalEl.querySelector(".js-custom-editor-pane");
        if (!formContainer) {
          return;
        }
        const previewSection = formContainer.querySelector(".js-live-preview-section");
        if (!previewSection) {
          return;
        }
        const isHidden = previewSection.classList.toggle("hidden");
        toggleBtn.textContent = isHidden ? t("editor.showPreview") : t("editor.hidePreview");
      });
    }
    const modelSelect = modalEl.querySelector(".js-llm-model-select");
    const tempSlider = modalEl.querySelector(".js-ai-temperature-slider");
    const tempValue = modalEl.querySelector(".js-ai-temperature-value");
    if (modelSelect) {
      modelSelect.addEventListener("change", () => {
        localStorage.setItem(AI_SETTINGS_KEYS.MODEL, modelSelect.value);
      });
    }
    if (tempSlider && tempValue) {
      const lastTemp = localStorage.getItem(AI_SETTINGS_KEYS.TEMPERATURE) || "0.7";
      tempSlider.value = lastTemp;
      tempValue.textContent = parseFloat(lastTemp).toFixed(1);
      tempSlider.addEventListener("input", () => {
        tempValue.textContent = parseFloat(tempSlider.value).toFixed(1);
      });
      tempSlider.addEventListener("change", () => {
        localStorage.setItem(AI_SETTINGS_KEYS.TEMPERATURE, tempSlider.value);
      });
    }
    window.addEventListener("keydown", (e) => {
      if (!isAiActionActive) {
        return;
      }
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "enter") {
        e.preventDefault();
        handleFloatyApply();
      } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "r") {
        e.preventDefault();
        handleFloatyRetry();
      } else if (e.key === "Escape") {
        e.preventDefault();
        handleFloatyDiscard();
      }
    });
  }
  async function openPromptEditor(context, promptId, initialState = null) {
    if (!modalEl) {
      console.error("Prompt editor modal element not found.");
      return;
    }
    if (!context.editorInterface) {
      console.error("`editorInterface` is missing from the context for openPromptEditor.");
      window.showAlert(t("prompt.errorNoInterface"));
      return;
    }
    currentContext = { ...context, initialState };
    currentPromptId = promptId;
    const placeholder = modalEl.querySelector(".js-prompt-placeholder");
    const customEditorPane = modalEl.querySelector(".js-custom-editor-pane");
    placeholder.classList.add("hidden");
    customEditorPane.classList.remove("hidden");
    try {
      await populateModelDropdown();
      await loadPrompt(promptId);
      modalEl.showModal();
    } catch (error) {
      console.error("Error loading prompt editor:", error);
      modalEl.showModal();
    }
  }

  // src/js/book-planner/editor-interface.js
  var createIframeEditorInterface = (contentWindow) => {
    const post = (type, payload) => contentWindow.postMessage({ type, payload }, window.location.origin);
    return {
      type: "iframe",
      // getting the current selection from the target editor to use as an insertion point.
      getSelectionInfo: (action) => new Promise((resolve) => {
        const listener = (event) => {
          if (event.source === contentWindow && event.data.type === "selectionResponse") {
            window.removeEventListener("message", listener);
            resolve(event.data.payload);
          }
        };
        window.addEventListener("message", listener);
        post("prepareForRephrase", { isRephrase: action === "rephrase" });
      }),
      getSelectionText: () => new Promise((resolve) => {
        const listener = (event) => {
          if (event.source === contentWindow && event.data.type === "selectionResponse") {
            window.removeEventListener("message", listener);
            resolve(event.data.payload.selectedText);
          }
        };
        window.addEventListener("message", listener);
        post("getSelectionText");
      }),
      getFullHtml: () => new Promise((resolve) => {
        const listener = (event) => {
          if (event.source === contentWindow && event.data.type === "fullHtmlResponse") {
            window.removeEventListener("message", listener);
            resolve(event.data.payload.html);
          }
        };
        window.addEventListener("message", listener);
        post("prepareForGetFullHtml");
      }),
      setEditable: (isEditable) => post("setEditable", { isEditable }),
      cleanupSuggestion: () => post("cleanupAiSuggestion"),
      discardAiSuggestion: (from, to, originalFragmentJson2) => post("discardAiSuggestion", { from, to, originalFragmentJson: originalFragmentJson2 }),
      replaceRangeWithSuggestion: (from, to, newContentHtml) => new Promise((resolve) => {
        const listener = (event) => {
          if (event.source === contentWindow && event.data.type === "replacementComplete") {
            window.removeEventListener("message", listener);
            resolve({ finalRange: event.data.payload.finalRange, endCoords: event.data.payload.endCoords });
          }
        };
        window.addEventListener("message", listener);
        post("replaceRange", { from, to, newContentHtml });
      })
    };
  };

  // src/js/book-planner/toolbar.js
  var activeContentWindow = null;
  var currentToolbarState = {};
  var toolbar = document.getElementById("top-toolbar");
  var wordCountEl = document.getElementById("js-word-count");
  var toolbarConfig = {};
  function setActiveContentWindow(contentWindow) {
    activeContentWindow = contentWindow;
  }
  function updateToolbarState(newState) {
    currentToolbarState = newState || {};
    const allBtns = toolbar.querySelectorAll(".js-toolbar-btn, .js-ai-action-btn");
    allBtns.forEach((btn) => {
      btn.disabled = true;
      btn.classList.remove("active");
    });
    const headingBtn = toolbar.querySelector(".js-heading-btn");
    if (headingBtn) headingBtn.textContent = t("editor.paragraph");
    wordCountEl.textContent = t("editor.noTextSelected");
    if (newState) {
      allBtns.forEach((btn) => {
        const cmd = btn.dataset.command;
        if (btn.classList.contains("js-ai-action-btn")) {
          if (btn.dataset.action === "rephrase") btn.disabled = !newState.isTextSelected;
          return;
        }
        btn.disabled = false;
        switch (cmd) {
          case "undo":
            btn.disabled = !newState.canUndo;
            break;
          case "redo":
            btn.disabled = !newState.canRedo;
            break;
          case "bold":
            btn.classList.toggle("active", newState.activeMarks.includes("strong"));
            break;
          case "italic":
            btn.classList.toggle("active", newState.activeMarks.includes("em"));
            break;
          case "underline":
            btn.classList.toggle("active", newState.activeMarks.includes("underline"));
            break;
          case "strike":
            btn.classList.toggle("active", newState.activeMarks.includes("strike"));
            break;
          case "blockquote":
            btn.classList.toggle("active", newState.activeNodes.includes("blockquote"));
            break;
          case "bullet_list":
            btn.classList.toggle("active", newState.activeMarks.includes("bullet_list"));
            break;
          case "ordered_list":
            btn.classList.toggle("active", newState.activeMarks.includes("ordered_list"));
            break;
        }
        if (btn.closest(".js-dropdown-container")) {
          btn.disabled = !newState.isTextSelected;
        }
      });
      if (headingBtn) {
        if (newState.headingLevel > 0) {
          headingBtn.textContent = `${t(`editor.heading${newState.headingLevel}`)}`;
        } else {
          headingBtn.textContent = t("editor.paragraph");
        }
        headingBtn.disabled = false;
      }
      if (newState.isTextSelected) {
        const words = newState.selectionText.trim().split(/\s+/).filter(Boolean);
        wordCountEl.textContent = t("editor.wordsSelected", { count: words.length });
      } else {
        wordCountEl.textContent = t("editor.noTextSelected");
      }
    }
  }
  function applyCommand(command, attrs = {}) {
    if (!activeContentWindow) return;
    activeContentWindow.postMessage({
      type: "command",
      payload: { command, attrs }
    }, window.location.origin);
  }
  function applyHighlight(color) {
    if (!activeContentWindow) return;
    activeContentWindow.postMessage({
      type: "command",
      payload: { command: "highlight", attrs: { color } }
    }, window.location.origin);
  }
  async function handleToolbarAction(button) {
    if (button.id === "js-open-dictionary-btn") {
      if (typeof toolbarConfig.onOpenDictionary === "function") {
        await toolbarConfig.onOpenDictionary();
      }
      return;
    }
    if (button.classList.contains("js-ai-action-btn")) {
      const action = button.dataset.action;
      const bookId = document.body.dataset.bookId;
      if (!bookId) {
        window.showAlert(t("editor.toolbar.errorNoProject"));
        return;
      }
      const bookData = await window.api.getOneBook(bookId);
      let settings = {};
      if (action === "rephrase" && bookData.rephrase_settings) {
        try {
          settings = JSON.parse(bookData.rephrase_settings);
        } catch (e) {
          console.error("Error parsing rephrase_settings JSON", e);
        }
      } else if (action === "translate" && bookData.translate_settings) {
        try {
          settings = JSON.parse(bookData.translate_settings);
        } catch (e) {
          console.error("Error parsing translate_settings JSON", e);
        }
      }
      if (!activeContentWindow) return;
      const editorInterface = createIframeEditorInterface(activeContentWindow);
      const selectionInfo = await editorInterface.getSelectionInfo(action);
      if (!selectionInfo) {
        console.log("Rephrase action cancelled: no text selected in the editor.");
        return;
      }
      const chapterId = toolbarConfig.getActiveChapterId ? toolbarConfig.getActiveChapterId() : null;
      const context = {
        selectedText: selectionInfo.selectedText,
        wordsBefore: selectionInfo.wordsBefore,
        wordsAfter: selectionInfo.wordsAfter,
        languageForPrompt: bookData.target_language || "English",
        activeEditorView: activeContentWindow,
        editorInterface,
        chapterId,
        bookId
      };
      openPromptEditor(context, action, settings);
      return;
    }
    if (!activeContentWindow && !button.closest(".js-dropdown-container")) {
      return;
    }
    const command = button.dataset.command;
    if (command) {
      applyCommand(command);
    } else if (button.classList.contains("js-highlight-option")) {
      applyHighlight(button.dataset.bg.replace("highlight-", ""));
      if (document.activeElement) document.activeElement.blur();
    } else if (button.classList.contains("js-heading-option")) {
      const level = parseInt(button.dataset.level, 10);
      applyCommand("heading", { level });
      if (document.activeElement) document.activeElement.blur();
    }
  }
  function setupTopToolbar(config = {}) {
    toolbarConfig = config;
    if (!toolbar) return;
    toolbar.addEventListener("mousedown", (event) => {
      const target = event.target;
      const dropdownTrigger = target.closest('button[tabindex="0"]');
      const inDropdownContent = target.closest(".dropdown-content");
      if (dropdownTrigger && dropdownTrigger.closest(".dropdown") || inDropdownContent) {
        return;
      }
      event.preventDefault();
    });
    toolbar.addEventListener("click", (event) => {
      const button = event.target.closest("button");
      if (!button || button.disabled) return;
      if (button.closest(".js-dropdown-container")) {
        if (button.classList.contains("js-toolbar-btn")) return;
      }
      handleToolbarAction(button);
    });
    updateToolbarState(null);
  }

  // src/js/book-planner/typography-settings.js
  var DEFAULTS = {
    font_family: "'Noto Serif', serif",
    text_size: "lg",
    line_height: "2",
    text_indent: "2",
    paragraph_spacing: "2",
    page_width: "3",
    text_align: "left"
  };
  var STORAGE_KEY = "typographySettings";
  var MAPPINGS = {
    text_size: { sm: "0.9rem", base: "1rem", lg: "1.2rem", xl: "1.6rem" },
    line_height: { "1": "1.5", "2": "1.65", "3": "1.8", "4": "2.0" },
    text_indent: { "1": "0", "2": "1.5em", "3": "2em", "4": "2.5em" },
    paragraph_spacing: { "1": "0", "2": "0.25em", "3": "0.50em", "4": "1em" },
    page_width: { "1": "24rem", "2": "36rem", "3": "48rem", "4": "56rem", "5": "none" }
  };
  function getTypographySettings() {
    try {
      const saved = localStorage.getItem(STORAGE_KEY);
      return saved ? { ...DEFAULTS, ...JSON.parse(saved) } : { ...DEFAULTS };
    } catch (e) {
      console.error("Failed to parse typography settings from localStorage", e);
      return { ...DEFAULTS };
    }
  }
  function saveSettings(settings) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
  }
  function generateTypographyStyleProperties(settings) {
    return {
      "--editor-font-family": settings.font_family,
      "--editor-font-size": MAPPINGS.text_size[settings.text_size] || MAPPINGS.text_size.lg,
      "--editor-line-height": MAPPINGS.line_height[settings.line_height] || MAPPINGS.line_height["2"],
      "--editor-text-indent": MAPPINGS.text_indent[settings.text_indent] || MAPPINGS.text_indent["2"],
      "--editor-paragraph-spacing": MAPPINGS.paragraph_spacing[settings.paragraph_spacing] || MAPPINGS.paragraph_spacing["2"],
      "--editor-page-width": MAPPINGS.page_width[settings.page_width] || MAPPINGS.page_width["3"],
      "--editor-text-align": settings.text_align
    };
  }
  function updateModalUI(modal, settings) {
    if (!modal) return;
    modal.querySelectorAll("select").forEach((select) => {
      const settingName = select.name;
      if (settings[settingName] !== void 0) {
        select.value = settings[settingName];
      }
    });
    modal.querySelectorAll(".btn-group[data-setting]").forEach((group) => {
      const settingName = group.dataset.setting;
      const currentValue = settings[settingName];
      group.querySelectorAll("button").forEach((button) => {
        button.classList.remove("btn-active");
        if (button.dataset.value === currentValue) {
          button.classList.add("btn-active");
        }
      });
    });
  }
  function setupTypographySettings({ buttonId, modalId, formId, applyCallback }) {
    const btn = document.getElementById(buttonId);
    const modal = document.getElementById(modalId);
    const form = document.getElementById(formId);
    if (!btn || !modal || !form) {
      console.error("Typography settings UI elements not found.");
      return;
    }
    let currentSettings = getTypographySettings();
    const applyStyles = () => {
      const properties = generateTypographyStyleProperties(currentSettings);
      applyCallback(properties, currentSettings);
    };
    applyStyles();
    updateModalUI(modal, currentSettings);
    btn.addEventListener("click", () => modal.showModal());
    const handleFormChange = () => {
      const formData = new FormData(form);
      const newSettings = { ...currentSettings };
      for (let [key, value] of formData.entries()) {
        newSettings[key] = value;
      }
      currentSettings = newSettings;
      saveSettings(currentSettings);
      applyStyles();
      updateModalUI(modal, currentSettings);
    };
    form.addEventListener("input", handleFormChange);
    form.addEventListener("click", (e) => {
      const button = e.target.closest("button[data-value]");
      if (!button) return;
      const group = button.closest("[data-setting]");
      if (!group) return;
      const settingName = group.dataset.setting;
      const value = button.dataset.value;
      if (currentSettings[settingName] !== value) {
        currentSettings[settingName] = value;
        saveSettings(currentSettings);
        applyStyles();
        updateModalUI(modal, currentSettings);
      }
    });
  }

  // src/js/dictionary/dictionary-modal.js
  var dictionaryModal;
  var dictionaryTableBody;
  var dictionaryNoEntriesMessage;
  var dictionaryAddRowBtn;
  var dictionaryDeleteSelectedBtn;
  var dictionarySaveBtn;
  var currentBookId;
  var currentDictionaryData = [];
  var currentSort = { sortBy: null, direction: "asc" };
  function updateCurrentDictionaryDataFromDOM() {
    const updatedData = [];
    Array.from(dictionaryTableBody.rows).forEach((row) => {
      const sourceInput = row.cells[1].querySelector("input");
      const targetInput = row.cells[2].querySelector("input");
      const typeSelect = row.cells[3].querySelector("select");
      updatedData.push({
        source: sourceInput ? sourceInput.value.trim() : "",
        target: targetInput ? targetInput.value.trim() : "",
        type: typeSelect ? typeSelect.value : "translation"
      });
    });
    currentDictionaryData = updatedData;
  }
  function renderDictionaryTable() {
    dictionaryTableBody.innerHTML = "";
    if (currentDictionaryData.length === 0) {
      dictionaryNoEntriesMessage.classList.remove("hidden");
      dictionaryDeleteSelectedBtn.disabled = true;
      return;
    }
    dictionaryNoEntriesMessage.classList.add("hidden");
    currentDictionaryData.forEach((entry, index) => {
      const row = dictionaryTableBody.insertRow();
      row.dataset.index = index;
      const checkboxCell = row.insertCell();
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.className = "checkbox checkbox-sm row-select-checkbox";
      checkboxCell.appendChild(checkbox);
      const sourceCell = row.insertCell();
      const sourceInput = document.createElement("input");
      sourceInput.type = "text";
      sourceInput.className = "input input-ghost input-sm w-full";
      sourceInput.value = entry.source;
      sourceInput.placeholder = t("dictionary.sourceTerm");
      sourceCell.appendChild(sourceInput);
      const targetCell = row.insertCell();
      const targetInput = document.createElement("input");
      targetInput.type = "text";
      targetInput.className = "input input-ghost input-sm w-full";
      targetInput.value = entry.target;
      targetInput.placeholder = t("dictionary.targetTranslation");
      targetCell.appendChild(targetInput);
      const typeCell = row.insertCell();
      const typeSelect = document.createElement("select");
      typeSelect.className = "select select-ghost select-sm w-full max-w-xs";
      const optionTranslation = document.createElement("option");
      optionTranslation.value = "translation";
      optionTranslation.setAttribute("data-i18n", "dictionary.typeTranslation");
      const optionRephrasing = document.createElement("option");
      optionRephrasing.value = "rephrasing";
      optionRephrasing.setAttribute("data-i18n", "dictionary.typeRephrasing");
      typeSelect.appendChild(optionTranslation);
      typeSelect.appendChild(optionRephrasing);
      typeSelect.value = entry.type || "translation";
      typeCell.appendChild(typeSelect);
      const actionsCell = row.insertCell();
      actionsCell.className = "w-12";
      const findReplaceBtn = document.createElement("button");
      findReplaceBtn.className = "js-dictionary-find-replace-btn btn btn-ghost btn-xs btn-square";
      findReplaceBtn.setAttribute("data-i18n-title", "dictionary.findAndReplace");
      findReplaceBtn.innerHTML = '<i class="bi bi-search-heart"></i>';
      actionsCell.appendChild(findReplaceBtn);
    });
    updateDeleteButtonState();
    updateSortButtonIcons();
    applyTranslationsTo(dictionaryTableBody);
  }
  function addRow(sourceText = "", targetText = "") {
    updateCurrentDictionaryDataFromDOM();
    currentDictionaryData.push({ source: sourceText, target: targetText, type: "translation" });
    renderDictionaryTable();
  }
  function deleteSelectedRows() {
    updateCurrentDictionaryDataFromDOM();
    const selectedCheckboxes = Array.from(dictionaryTableBody.querySelectorAll(".row-select-checkbox:checked"));
    if (selectedCheckboxes.length === 0) return;
    const indicesToDelete = selectedCheckboxes.map((cb) => parseInt(cb.closest("tr").dataset.index, 10)).sort((a, b) => b - a);
    indicesToDelete.forEach((index) => {
      currentDictionaryData.splice(index, 1);
    });
    renderDictionaryTable();
  }
  function updateDeleteButtonState() {
    const anySelected = dictionaryTableBody.querySelectorAll(".row-select-checkbox:checked").length > 0;
    dictionaryDeleteSelectedBtn.disabled = !anySelected;
  }
  async function saveDictionary() {
    const updatedData = [];
    Array.from(dictionaryTableBody.rows).forEach((row) => {
      const sourceInput = row.cells[1].querySelector("input");
      const targetInput = row.cells[2].querySelector("input");
      const typeSelect = row.cells[3].querySelector("select");
      if (sourceInput.value.trim() || targetInput.value.trim()) {
        updatedData.push({
          source: sourceInput.value.trim(),
          target: targetInput.value.trim(),
          type: typeSelect ? typeSelect.value : "translation"
        });
      }
    });
    currentDictionaryData = updatedData;
    try {
      await window.api.saveBookDictionary(currentBookId, currentDictionaryData);
      dictionaryModal.close();
    } catch (error) {
      console.error("Failed to save dictionary:", error);
      window.showAlert(t("common.error") + ": " + error.message);
    }
  }
  function sortDictionary(sortBy, direction, shouldRender = true) {
    updateCurrentDictionaryDataFromDOM();
    currentDictionaryData.sort((a, b) => {
      const valA = (a[sortBy] || (sortBy === "type" ? "translation" : "")).toLowerCase();
      const valB = (b[sortBy] || (sortBy === "type" ? "translation" : "")).toLowerCase();
      if (valA < valB) return direction === "asc" ? -1 : 1;
      if (valA > valB) return direction === "asc" ? 1 : -1;
      return 0;
    });
    currentSort = { sortBy, direction };
    if (shouldRender) {
      renderDictionaryTable();
    }
  }
  function updateSortButtonIcons() {
    const sortButtons = dictionaryModal.querySelectorAll(".js-sort-btn");
    sortButtons.forEach((button) => {
      const sortBy = button.dataset.sortBy;
      const icon = button.querySelector("i");
      icon.className = "bi bi-sort-alpha-down";
      button.dataset.sortDirection = "asc";
      if (currentSort.sortBy === sortBy) {
        if (currentSort.direction === "asc") {
          icon.className = "bi bi-sort-alpha-down";
          button.dataset.sortDirection = "asc";
        } else {
          icon.className = "bi bi-sort-alpha-up";
          button.dataset.sortDirection = "desc";
        }
      }
    });
  }
  function initDictionaryModal(bookId) {
    currentBookId = bookId;
    dictionaryModal = document.getElementById("dictionary-modal");
    dictionaryTableBody = document.getElementById("dictionary-table-body");
    dictionaryNoEntriesMessage = document.getElementById("dictionary-no-entries");
    dictionaryAddRowBtn = document.getElementById("dictionary-add-row-btn");
    dictionaryDeleteSelectedBtn = document.getElementById("dictionary-delete-selected-btn");
    dictionarySaveBtn = document.getElementById("dictionary-save-btn");
    if (!dictionaryModal) {
      console.error("Dictionary modal element not found.");
      return;
    }
    applyTranslationsTo(dictionaryModal);
    dictionaryAddRowBtn.addEventListener("click", () => addRow());
    dictionaryDeleteSelectedBtn.addEventListener("click", deleteSelectedRows);
    dictionarySaveBtn.addEventListener("click", saveDictionary);
    dictionaryModal.querySelectorAll(".js-sort-btn").forEach((button) => {
      button.addEventListener("click", (event) => {
        const sortBy = event.currentTarget.dataset.sortBy;
        let direction = event.currentTarget.dataset.sortDirection;
        if (currentSort.sortBy === sortBy) {
          direction = currentSort.direction === "asc" ? "desc" : "asc";
        } else {
          direction = "asc";
        }
        sortDictionary(sortBy, direction);
      });
    });
    dictionaryTableBody.addEventListener("change", (event) => {
      if (event.target.classList.contains("row-select-checkbox")) {
        updateDeleteButtonState();
      }
    });
    dictionaryTableBody.addEventListener("click", (event) => {
      const findReplaceBtn = event.target.closest(".js-dictionary-find-replace-btn");
      if (findReplaceBtn) {
        const row = findReplaceBtn.closest("tr");
        const sourceInput = row.querySelector("td:nth-child(2) input");
        const targetInput = row.querySelector("td:nth-child(3) input");
        const sourceTerm = sourceInput.value.trim();
        const targetTerm = targetInput.value.trim();
        if (sourceTerm) {
          document.body.dispatchEvent(new CustomEvent("dictionary:find-replace", {
            detail: {
              find: sourceTerm,
              replace: targetTerm
            }
          }));
          dictionaryModal.close();
        }
      }
    });
    dictionaryModal.addEventListener("close", () => {
      currentSort = { sortBy: null, direction: "asc" };
    });
  }
  async function openDictionaryModal(selectedText = "", sourceOrTarget = "") {
    if (!dictionaryModal) return;
    try {
      currentSort = { sortBy: null, direction: "asc" };
      const data = await window.api.getBookDictionary(currentBookId);
      currentDictionaryData = data || [];
      if (selectedText) {
        if (sourceOrTarget === "source") {
          currentDictionaryData.push({ source: selectedText, target: "", type: "translation" });
        } else if (sourceOrTarget === "target") {
          currentDictionaryData.push({ source: "", target: selectedText, type: "translation" });
        }
      }
      renderDictionaryTable();
      dictionaryModal.showModal();
    } catch (error) {
      console.error("Failed to open or load dictionary modal:", error);
      window.showAlert(t("common.error") + ": " + error.message);
    }
  }

  // src/utils/modal-loader.js
  async function loadModals(modalNames, containerId) {
    const container = document.getElementById(containerId);
    if (!container) {
      console.error(`Modal container #${containerId} not found.`);
      return;
    }
    try {
      const templates = await Promise.all(
        modalNames.map((name) => window.api.getTemplate(`modals/${name}`))
      );
      container.innerHTML = templates.join("\n");
    } catch (error) {
      console.error("Failed to load modal templates:", error);
      container.innerHTML = '<p class="text-error">Error loading modals.</p>';
    }
  }

  // src/js/book-planner/modals.js
  function showConfirmationModal(title, message, options = {}) {
    return new Promise((resolve) => {
      const modal = document.getElementById("confirmation-modal");
      const titleEl = document.getElementById("confirmation-modal-title");
      const contentEl = document.getElementById("confirmation-modal-content");
      const confirmBtn = document.getElementById("confirmation-modal-confirm-btn");
      const cancelBtn = document.getElementById("confirmation-modal-cancel-btn");
      const declineBtn = document.getElementById("confirmation-modal-decline-btn");
      const newConfirmBtn = confirmBtn.cloneNode(true);
      confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
      const newCancelBtn = cancelBtn.cloneNode(true);
      cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
      const newDeclineBtn = declineBtn.cloneNode(true);
      declineBtn.parentNode.replaceChild(newDeclineBtn, declineBtn);
      titleEl.innerHTML = title;
      contentEl.innerHTML = message;
      if (options.showDecline) {
        newDeclineBtn.classList.remove("hidden");
        if (options.declineKey) {
          newDeclineBtn.textContent = t(options.declineKey);
        }
      } else {
        newDeclineBtn.classList.add("hidden");
      }
      const cleanup = () => {
        modal.removeEventListener("close", handleClose);
        newConfirmBtn.removeEventListener("click", handleConfirm);
        newCancelBtn.removeEventListener("click", handleCancel);
        newDeclineBtn.removeEventListener("click", handleDecline);
      };
      const handleConfirm = () => {
        cleanup();
        modal.close();
        resolve(true);
      };
      const handleCancel = () => {
        cleanup();
        modal.close();
        resolve(false);
      };
      const handleDecline = () => {
        cleanup();
        modal.close();
        resolve("decline");
      };
      const handleClose = () => {
        cleanup();
        resolve(false);
      };
      newConfirmBtn.addEventListener("click", handleConfirm, { once: true });
      newCancelBtn.addEventListener("click", handleCancel, { once: true });
      newDeclineBtn.addEventListener("click", handleDecline, { once: true });
      modal.addEventListener("close", handleClose, { once: true });
      modal.showModal();
    });
  }
  function showInputModal(title, label, initialValue = "") {
    return new Promise((resolve) => {
      const modal = document.getElementById("input-modal");
      const titleEl = document.getElementById("input-modal-title");
      const labelEl = document.getElementById("input-modal-label").querySelector("span");
      const inputEl = document.getElementById("input-modal-input");
      const form = document.getElementById("input-modal-form");
      titleEl.textContent = title;
      labelEl.textContent = label;
      inputEl.value = initialValue;
      const handleSubmit = (e) => {
        e.preventDefault();
        const value = inputEl.value.trim();
        resolve(value);
        cleanup();
      };
      const handleClose = () => {
        resolve(null);
        cleanup();
      };
      const cleanup = () => {
        modal.close();
        form.removeEventListener("submit", handleSubmit);
        modal.removeEventListener("close", handleClose);
      };
      form.addEventListener("submit", handleSubmit);
      modal.addEventListener("close", handleClose);
      modal.showModal();
      inputEl.focus();
      inputEl.select();
    });
  }

  // src/js/book-planner/scroll-sync.js
  var isScrollingProgrammatically = false;
  function syncChapterScroll(chapterId, direction) {
    const sourceChapterEl = document.getElementById(`source-chapter-scroll-target-${chapterId}`);
    const targetChapterEl = document.getElementById(`target-chapter-scroll-target-${chapterId}`);
    const sourceContainer = document.getElementById("js-source-column-container");
    const targetContainer = document.getElementById("js-target-column-container");
    if (!sourceChapterEl || !targetChapterEl || !sourceContainer || !targetContainer) {
      console.warn(`Could not find elements for chapter scroll sync: ${chapterId}`);
      return;
    }
    let sourceEl, targetEl, sourceWrapper, targetWrapper;
    if (direction === "source-to-target") {
      sourceEl = sourceContainer;
      targetEl = targetContainer;
      sourceWrapper = sourceChapterEl;
      targetWrapper = targetChapterEl;
    } else {
      sourceEl = targetContainer;
      targetEl = sourceContainer;
      sourceWrapper = targetChapterEl;
      targetWrapper = sourceChapterEl;
    }
    const sourceContainerRect = sourceEl.getBoundingClientRect();
    const sourceWrapperRect = sourceWrapper.getBoundingClientRect();
    const relativeTop = sourceWrapperRect.top - sourceContainerRect.top;
    const targetContainerRect = targetEl.getBoundingClientRect();
    const targetWrapperRect = targetWrapper.getBoundingClientRect();
    const targetAbsoluteTop = targetWrapperRect.top;
    const desiredScrollTop = targetEl.scrollTop + (targetAbsoluteTop - targetContainerRect.top) - relativeTop;
    targetEl.scrollTo({
      top: desiredScrollTop,
      behavior: "smooth"
    });
  }
  function scrollToChapter(chapterId, setActiveChapterIdCallback) {
    const sourceTarget = document.getElementById(`source-chapter-scroll-target-${chapterId}`);
    const targetTarget = document.getElementById(`target-chapter-scroll-target-${chapterId}`);
    const sourceContainer = document.getElementById("js-source-column-container");
    const targetContainer = document.getElementById("js-target-column-container");
    isScrollingProgrammatically = true;
    const scrollToTarget = (container, target) => {
      if (target && container) {
        const containerRect = container.getBoundingClientRect();
        const targetRect = target.getBoundingClientRect();
        const offsetTop = targetRect.top - containerRect.top;
        const scrollPosition = container.scrollTop + offsetTop - 100;
        container.scrollTo({
          top: scrollPosition,
          behavior: "smooth"
        });
      }
    };
    scrollToTarget(sourceContainer, sourceTarget);
    scrollToTarget(targetContainer, targetTarget);
    setActiveChapterIdCallback(chapterId);
    setTimeout(() => {
      isScrollingProgrammatically = false;
    }, 1e3);
  }
  function scrollToTargetMarker(chapterId, markerId, markerType, chapterEditorViews3) {
    const viewInfo = chapterEditorViews3.get(chapterId.toString());
    if (!viewInfo || !viewInfo.isReady) {
      console.warn(`Iframe for chapter ${chapterId} is not ready or not found.`);
      return;
    }
    const searchText = markerType === "opening" ? `[[#${markerId}]]` : `{{#${markerId}}}`;
    viewInfo.contentWindow.postMessage({
      type: "findAndScrollToText",
      payload: { text: searchText }
    }, window.location.origin);
  }
  function scrollToSourceMarker(markerId, markerType) {
    const sourceContainer = document.getElementById("js-source-column-container");
    if (!sourceContainer) return;
    const selector = `.translation-marker-link[data-marker-id="${markerId}"][data-marker-type="${markerType}"]`;
    const markerLink = sourceContainer.querySelector(selector);
    if (markerLink) {
      markerLink.scrollIntoView({ behavior: "smooth", block: "center" });
      markerLink.classList.add("search-highlight-active");
      setTimeout(() => {
        markerLink.classList.remove("search-highlight-active");
      }, 2e3);
    } else {
      console.warn(`Source marker with ID ${markerId} and type ${markerType} not found.`);
    }
  }
  function setupIntersectionObserver(setActiveChapterIdCallback) {
    const container = document.getElementById("js-source-column-container");
    const navDropdown = document.getElementById("js-chapter-nav-dropdown");
    const observer = new IntersectionObserver((entries) => {
      if (isScrollingProgrammatically) return;
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          const chapterId = entry.target.dataset.chapterId;
          setActiveChapterIdCallback(chapterId, (newActiveId) => {
            navDropdown.value = newActiveId;
          });
        }
      });
    }, {
      root: container,
      rootMargin: "-40% 0px -60% 0px",
      threshold: 0
    });
    container.querySelectorAll(".manuscript-chapter-item").forEach((el) => observer.observe(el));
  }

  // src/js/book-planner/search.js
  var globalSearchMatches = [];
  var currentMatchIndex = -1;
  var searchResponsesPending = 0;
  function setupSearch(chapterEditorViews3, registerSearchResultHandler) {
    const searchBtn = document.getElementById("js-search-btn");
    const searchBar = document.getElementById("js-search-bar");
    const searchInput2 = document.getElementById("js-search-input");
    const searchCloseBtn = document.getElementById("js-search-close-btn");
    const searchPrevBtn = document.getElementById("js-search-prev-btn");
    const searchNextBtn = document.getElementById("js-search-next-btn");
    const searchResultsCount = document.getElementById("js-search-results-count");
    const searchScopeRadios = document.querySelectorAll('input[name="search-scope"]');
    const toggleSearchBar = (show) => {
      if (show) {
        document.getElementById("js-search-replace-bar").classList.add("hidden");
        searchBar.classList.remove("hidden");
        searchInput2.focus();
        searchInput2.select();
      } else {
        searchBar.classList.add("hidden");
        clearSearch2();
      }
    };
    const clearHighlightsInSource = () => {
      const sourceContainer = document.getElementById("js-source-column-container");
      const marks = sourceContainer.querySelectorAll("mark.search-highlight");
      marks.forEach((mark) => {
        const parent = mark.parentNode;
        parent.replaceChild(document.createTextNode(mark.textContent), mark);
        parent.normalize();
      });
    };
    const clearSearch2 = () => {
      clearHighlightsInSource();
      chapterEditorViews3.forEach((view) => {
        if (view.isReady) {
          view.contentWindow.postMessage({ type: "search:clear" }, window.location.origin);
        }
      });
      globalSearchMatches = [];
      currentMatchIndex = -1;
      searchResultsCount.textContent = "";
      searchPrevBtn.disabled = true;
      searchNextBtn.disabled = true;
    };
    const findAndHighlightInSource = (query) => {
      clearHighlightsInSource();
      if (!query) return [];
      const sourceContainer = document.getElementById("js-source-column-container");
      const matches = [];
      const walker = document.createTreeWalker(sourceContainer, NodeFilter.SHOW_TEXT, null, false);
      const nodesToProcess = [];
      let node;
      while (node = walker.nextNode()) {
        if (node.parentElement.closest("script, style")) continue;
        if (new RegExp(query, "gi").test(node.textContent)) {
          nodesToProcess.push(node);
        }
      }
      nodesToProcess.forEach((textNode) => {
        const text = textNode.textContent;
        const fragment = document.createDocumentFragment();
        let lastIndex = 0;
        const regex = new RegExp(query, "gi");
        let match;
        while ((match = regex.exec(text)) !== null) {
          if (match.index > lastIndex) {
            fragment.appendChild(document.createTextNode(text.substring(lastIndex, match.index)));
          }
          const mark = document.createElement("mark");
          mark.className = "search-highlight";
          mark.textContent = match[0];
          fragment.appendChild(mark);
          matches.push(mark);
          lastIndex = regex.lastIndex;
        }
        if (lastIndex < text.length) {
          fragment.appendChild(document.createTextNode(text.substring(lastIndex)));
        }
        if (textNode.parentNode) {
          textNode.parentNode.replaceChild(fragment, textNode);
        }
      });
      return matches;
    };
    const updateSearchResultsUI = () => {
      const total = globalSearchMatches.length;
      searchResultsCount.textContent = total > 0 ? t("editor.searchBar.results", { current: currentMatchIndex + 1, total }) : t("editor.searchBar.noResults");
      searchPrevBtn.disabled = total <= 1;
      searchNextBtn.disabled = total <= 1;
    };
    const navigateToMatch2 = (index) => {
      if (index < 0 || index >= globalSearchMatches.length) return;
      if (currentMatchIndex !== -1) {
        const oldMatch = globalSearchMatches[currentMatchIndex];
        if (oldMatch.scope === "source") {
          oldMatch.element.classList.remove("search-highlight-active");
        } else {
          const view = chapterEditorViews3.get(oldMatch.chapterId.toString());
          if (view?.isReady) {
            view.contentWindow.postMessage({ type: "search:navigateTo", payload: { matchIndex: oldMatch.matchIndex, isActive: false } }, window.location.origin);
          }
        }
      }
      currentMatchIndex = index;
      const newMatch = globalSearchMatches[currentMatchIndex];
      if (newMatch.scope === "source") {
        newMatch.element.classList.add("search-highlight-active");
        newMatch.element.scrollIntoView({ behavior: "smooth", block: "center" });
      } else {
        const view = chapterEditorViews3.get(newMatch.chapterId.toString());
        if (view?.isReady) {
          view.contentWindow.postMessage({ type: "search:navigateTo", payload: { matchIndex: newMatch.matchIndex, isActive: true } }, window.location.origin);
        }
      }
      updateSearchResultsUI();
    };
    const debounce5 = (func, delay) => {
      let timeout;
      return function(...args) {
        const context = this;
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(context, args), delay);
      };
    };
    const startSearch2 = debounce5(() => {
      const query = searchInput2.value;
      const scope = document.querySelector('input[name="search-scope"]:checked').value;
      clearSearch2();
      if (query.length < 2) return;
      if (scope === "source") {
        const matches = findAndHighlightInSource(query);
        globalSearchMatches = matches.map((el) => ({ scope: "source", element: el }));
        if (globalSearchMatches.length > 0) navigateToMatch2(0);
        updateSearchResultsUI();
      } else {
        searchResponsesPending = chapterEditorViews3.size;
        globalSearchMatches = [];
        chapterEditorViews3.forEach((view) => {
          if (view.isReady) {
            view.contentWindow.postMessage({ type: "search:findAndHighlight", payload: { query } }, window.location.origin);
          } else {
            searchResponsesPending--;
          }
        });
      }
    }, 300);
    searchBtn.addEventListener("click", () => toggleSearchBar(true));
    searchCloseBtn.addEventListener("click", () => toggleSearchBar(false));
    searchInput2.addEventListener("input", startSearch2);
    searchScopeRadios.forEach((radio) => radio.addEventListener("change", startSearch2));
    searchNextBtn.addEventListener("click", () => navigateToMatch2((currentMatchIndex + 1) % globalSearchMatches.length));
    searchPrevBtn.addEventListener("click", () => navigateToMatch2((currentMatchIndex - 1 + globalSearchMatches.length) % globalSearchMatches.length));
    searchInput2.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if (e.shiftKey) {
          if (!searchPrevBtn.disabled) searchPrevBtn.click();
        } else {
          if (!searchNextBtn.disabled) searchNextBtn.click();
        }
      }
    });
    registerSearchResultHandler((payload) => {
      const { chapterId, matchCount } = payload;
      for (let i = 0; i < matchCount; i++) {
        globalSearchMatches.push({ scope: "target", chapterId, matchIndex: i });
      }
      searchResponsesPending--;
      if (searchResponsesPending === 0) {
        const chapterOrder = Array.from(document.querySelectorAll(".manuscript-chapter-item[data-chapter-id]")).map((el) => el.dataset.chapterId);
        globalSearchMatches.sort((a, b) => {
          const orderA = chapterOrder.indexOf(a.chapterId.toString());
          const orderB = chapterOrder.indexOf(b.chapterId.toString());
          if (orderA !== orderB) return orderA - orderB;
          return a.matchIndex - b.matchIndex;
        });
        if (globalSearchMatches.length > 0) navigateToMatch2(0);
        updateSearchResultsUI();
      }
    });
    return {
      toggle: toggleSearchBar,
      isHidden: () => searchBar.classList.contains("hidden")
    };
  }

  // src/js/book-planner/search-replace.js
  var chapterEditorViews;
  var globalMatches = [];
  var currentMatchIndex2 = -1;
  var searchResponsesPending2 = 0;
  var resultHandlerCallback;
  var searchReplaceBar;
  var searchInput;
  var replaceInput;
  var prevBtn;
  var nextBtn;
  var replaceBtn;
  var replaceAllBtn;
  var resultsCount;
  var closeBtn;
  var caseSensitiveBtn;
  var debounce3 = (func, delay) => {
    let timeout;
    return function(...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), delay);
    };
  };
  function initializeUI() {
    searchReplaceBar = document.getElementById("js-search-replace-bar");
    searchInput = document.getElementById("js-search-replace-input");
    replaceInput = document.getElementById("js-replace-input");
    prevBtn = document.getElementById("js-search-replace-prev-btn");
    nextBtn = document.getElementById("js-search-replace-next-btn");
    replaceBtn = document.getElementById("js-replace-btn");
    replaceAllBtn = document.getElementById("js-replace-all-btn");
    resultsCount = document.getElementById("js-search-replace-results-count");
    closeBtn = document.getElementById("js-search-replace-close-btn");
    caseSensitiveBtn = document.getElementById("js-case-sensitive-btn");
  }
  function toggleSearchReplaceBar(show) {
    if (show) {
      document.getElementById("js-search-bar").classList.add("hidden");
      searchReplaceBar.classList.remove("hidden");
      searchInput.focus();
      searchInput.select();
    } else {
      searchReplaceBar.classList.add("hidden");
      clearSearch();
    }
  }
  function openSearchAndReplaceWithValues(findValue, replaceValue) {
    toggleSearchReplaceBar(true);
    searchInput.value = findValue || "";
    replaceInput.value = replaceValue || "";
    startSearch();
  }
  function clearSearch() {
    chapterEditorViews.forEach((view) => {
      if (view.isReady) {
        view.contentWindow.postMessage({ type: "search-replace:clear" }, window.location.origin);
      }
    });
    globalMatches = [];
    currentMatchIndex2 = -1;
    resultsCount.textContent = "";
    prevBtn.disabled = true;
    nextBtn.disabled = true;
    replaceBtn.disabled = true;
    replaceAllBtn.disabled = true;
  }
  function updateResultsUI() {
    const total = globalMatches.length;
    resultsCount.textContent = total > 0 ? t("editor.searchReplace.results", { current: currentMatchIndex2 + 1, total }) : t("editor.searchReplace.noResults");
    const hasMatches = total > 0;
    prevBtn.disabled = !hasMatches;
    nextBtn.disabled = !hasMatches;
    replaceBtn.disabled = !hasMatches;
    replaceAllBtn.disabled = !hasMatches;
  }
  function navigateToMatch(index) {
    if (index < 0 || index >= globalMatches.length) return;
    if (currentMatchIndex2 !== -1) {
      const oldMatch = globalMatches[currentMatchIndex2];
      const oldView = chapterEditorViews.get(oldMatch.chapterId.toString());
      if (oldView?.isReady) {
        oldView.contentWindow.postMessage({ type: "search-replace:navigateTo", payload: { matchIndex: oldMatch.matchIndex, isActive: false } }, window.location.origin);
      }
    }
    currentMatchIndex2 = index;
    const newMatch = globalMatches[currentMatchIndex2];
    const newView = chapterEditorViews.get(newMatch.chapterId.toString());
    if (newView?.isReady) {
      newView.contentWindow.postMessage({ type: "search-replace:navigateTo", payload: { matchIndex: newMatch.matchIndex, isActive: true } }, window.location.origin);
    }
    updateResultsUI();
  }
  var startSearch = debounce3(() => {
    const query = searchInput.value;
    clearSearch();
    if (query.length < 1) {
      updateResultsUI();
      return;
    }
    const caseSensitive = caseSensitiveBtn.classList.contains("active");
    searchResponsesPending2 = 0;
    chapterEditorViews.forEach((view) => {
      if (view.isReady) {
        searchResponsesPending2++;
        view.contentWindow.postMessage({ type: "search-replace:find", payload: { query, caseSensitive } }, window.location.origin);
      }
    });
  }, 300);
  function handleReplace() {
    if (currentMatchIndex2 === -1) return;
    const match = globalMatches[currentMatchIndex2];
    const view = chapterEditorViews.get(match.chapterId.toString());
    if (view?.isReady) {
      view.contentWindow.postMessage({
        type: "search-replace:replace",
        payload: {
          matchIndex: match.matchIndex,
          replaceText: replaceInput.value
        }
      }, window.location.origin);
    }
  }
  function handleReplaceAll() {
    const query = searchInput.value;
    if (query.length < 1) return;
    const replaceText = replaceInput.value;
    const caseSensitive = caseSensitiveBtn.classList.contains("active");
    searchResponsesPending2 = 0;
    let totalReplaced = 0;
    chapterEditorViews.forEach((view) => {
      if (view.isReady) {
        searchResponsesPending2++;
        view.contentWindow.postMessage({ type: "search-replace:replaceAll", payload: { query, replaceText, caseSensitive } }, window.location.origin);
      }
    });
    const handler = (type, payload) => {
      if (type === "search-replace:replacedAll") {
        totalReplaced += payload.count;
        searchResponsesPending2--;
        if (searchResponsesPending2 === 0) {
          clearSearch();
          resultsCount.textContent = t("editor.searchReplace.replaceAllResult", { count: totalReplaced });
          resultHandlerCallback = null;
        }
      }
    };
    resultHandlerCallback = handler;
  }
  function handleIframeResponse(type, payload) {
    switch (type) {
      case "search-replace:results": {
        const { chapterId, matchCount } = payload;
        for (let i = 0; i < matchCount; i++) {
          globalMatches.push({ chapterId, matchIndex: i });
        }
        searchResponsesPending2--;
        if (searchResponsesPending2 === 0) {
          const chapterOrder = Array.from(document.querySelectorAll(".manuscript-chapter-item[data-chapter-id]")).map((el) => el.dataset.chapterId);
          globalMatches.sort((a, b) => {
            const orderA = chapterOrder.indexOf(a.chapterId.toString());
            const orderB = chapterOrder.indexOf(b.chapterId.toString());
            if (orderA !== orderB) return orderA - orderB;
            return a.matchIndex - b.matchIndex;
          });
          if (globalMatches.length > 0) {
            navigateToMatch(0);
          } else {
            updateResultsUI();
          }
        }
        break;
      }
      case "search-replace:replaced": {
        startSearch();
        break;
      }
      case "search-replace:replacedAll": {
        if (resultHandlerCallback) {
          resultHandlerCallback(type, payload);
        }
        break;
      }
    }
  }
  function setupSearchAndReplace(views, registerHandler) {
    chapterEditorViews = views;
    registerHandler((type, payload) => handleIframeResponse(type, payload));
    initializeUI();
    const searchReplaceBtn = document.getElementById("js-search-replace-btn");
    searchReplaceBtn.addEventListener("click", () => toggleSearchReplaceBar(true));
    closeBtn.addEventListener("click", () => toggleSearchReplaceBar(false));
    searchInput.addEventListener("input", startSearch);
    caseSensitiveBtn.addEventListener("click", () => {
      caseSensitiveBtn.classList.toggle("active");
      startSearch();
    });
    nextBtn.addEventListener("click", () => navigateToMatch((currentMatchIndex2 + 1) % globalMatches.length));
    prevBtn.addEventListener("click", () => navigateToMatch((currentMatchIndex2 - 1 + globalMatches.length) % globalMatches.length));
    replaceBtn.addEventListener("click", handleReplace);
    replaceAllBtn.addEventListener("click", handleReplaceAll);
    searchInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if (e.shiftKey) {
          if (!prevBtn.disabled) prevBtn.click();
        } else {
          if (!nextBtn.disabled) nextBtn.click();
        }
      }
      if (e.key === "Escape" && !searchReplaceBar.classList.contains("hidden")) {
        toggleSearchReplaceBar(false);
      }
    });
    replaceInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if (!replaceBtn.disabled) {
          handleReplace();
        }
      }
    });
    return {
      openWithValues: openSearchAndReplaceWithValues,
      toggle: toggleSearchReplaceBar,
      isHidden: () => searchReplaceBar.classList.contains("hidden")
    };
  }

  // src/js/book-planner/spellcheck.js
  async function setupSpellcheckDropdown() {
    const dropdown = document.getElementById("js-spellcheck-lang-dropdown");
    if (!dropdown) {
      console.error("[setupSpellcheckDropdown] Dropdown element not found.");
      return;
    }
    try {
      const availableLangs = await window.api.getAvailableSpellCheckerLanguages();
      const currentLang = await window.api.getCurrentSpellCheckerLanguage();
      dropdown.innerHTML = "";
      const disableOption = new Option("Disable Spellcheck", "");
      dropdown.appendChild(disableOption);
      const supportedLanguages = await window.api.getSupportedLanguages();
      availableLangs.sort().forEach((code) => {
        const name = supportedLanguages[code] || code;
        const option = new Option(name, code);
        dropdown.appendChild(option);
      });
      dropdown.value = currentLang || "";
      dropdown.addEventListener("change", async () => {
        const selectedLang = dropdown.value;
        try {
          await window.api.setSpellCheckerLanguage(selectedLang);
        } catch (error) {
          console.error("[Spellcheck] Error setting language:", error);
          window.showAlert("Could not set spellcheck language.");
        }
      });
    } catch (error) {
      console.error("[setupSpellcheckDropdown] Failed to initialize:", error);
      dropdown.innerHTML = `<option>${t("common.error")}</option>`;
      dropdown.disabled = true;
    }
  }

  // src/js/book-planner/dictionary-handler.js
  async function handleOpenDictionaryWithSelection(activeEditor2, currentSourceSelection2) {
    let selectedText = "";
    let sourceOrTarget = "";
    if (activeEditor2) {
      const editorInterface = createIframeEditorInterface(activeEditor2);
      try {
        const iframeSelectedText = await editorInterface.getSelectionText();
        if (iframeSelectedText && iframeSelectedText.length > 0) {
          selectedText = iframeSelectedText;
          sourceOrTarget = "target";
        }
      } catch (error) {
        console.error("Error getting selection from iframe:", error);
      }
    }
    if (!selectedText && currentSourceSelection2.hasSelection && currentSourceSelection2.text.length > 0) {
      selectedText = currentSourceSelection2.text;
      sourceOrTarget = "source";
    }
    openDictionaryModal(selectedText, sourceOrTarget);
  }

  // src/js/book-planner/shortcuts.js
  function setupShortcuts(dependencies) {
    const {
      searchAPI,
      searchReplaceAPI,
      getActiveEditor: getActiveEditor2,
      getLastFocusedSourceEditor: getLastFocusedSourceEditor2,
      chapterEditorViews: chapterEditorViews3
    } = dependencies;
    window.addEventListener("keydown", (e) => {
      const activeEl = document.activeElement;
      const isModalOpen = document.querySelector(".modal[open], .modal-open");
      if (isModalOpen) {
        return;
      }
      if (e.key === "Escape") {
        const isSearchVisible = !searchAPI.isHidden();
        const isSearchReplaceVisible = !searchReplaceAPI.isHidden();
        if (isSearchVisible) {
          searchAPI.toggle(false);
          e.preventDefault();
        }
        if (isSearchReplaceVisible) {
          searchReplaceAPI.toggle(false);
          e.preventDefault();
        }
        if (isSearchVisible || isSearchReplaceVisible) {
          return;
        }
      }
      if (e.ctrlKey || e.metaKey) {
        const isGenericInputFocused = activeEl && (activeEl.tagName === "INPUT" || activeEl.tagName === "TEXTAREA") && !activeEl.closest("#js-search-bar") && !activeEl.closest("#js-search-replace-bar");
        if (isGenericInputFocused && ["f", "h", "t"].includes(e.key.toLowerCase())) {
          return;
        }
        switch (e.key.toLowerCase()) {
          case "f":
            e.preventDefault();
            if (searchReplaceAPI.isHidden()) {
              searchAPI.toggle(true);
            }
            break;
          case "h":
            e.preventDefault();
            searchReplaceAPI.toggle(true);
            break;
          case "1": {
            e.preventDefault();
            const lastFocused = getLastFocusedSourceEditor2();
            if (lastFocused) {
              lastFocused.focus();
            } else {
              const sourceContainer = document.getElementById("js-source-column-container");
              const firstEditor = sourceContainer?.querySelector(".source-content-readonly");
              if (firstEditor) {
                firstEditor.focus();
              } else if (sourceContainer) {
                sourceContainer.focus({ preventScroll: true });
              }
            }
            break;
          }
          case "2": {
            e.preventDefault();
            const editorToFocus = getActiveEditor2();
            if (editorToFocus) {
              editorToFocus.postMessage({ type: "focusEditor" }, window.location.origin);
            } else {
              const navDropdown = document.getElementById("js-chapter-nav-dropdown");
              const activeChapterId2 = navDropdown ? navDropdown.value : null;
              if (activeChapterId2) {
                const viewInfo = chapterEditorViews3.get(activeChapterId2.toString());
                if (viewInfo && viewInfo.isReady) {
                  viewInfo.contentWindow.postMessage({ type: "focusEditor" }, window.location.origin);
                }
              }
            }
            break;
          }
          case "t": {
            const activeTargetEditor = getActiveEditor2();
            if (activeTargetEditor) {
              e.preventDefault();
              activeTargetEditor.postMessage({ type: "triggerTranslate" }, window.location.origin);
            }
            break;
          }
        }
      }
    });
  }

  // src/js/book-planner/chapter-main.js
  var debounce4 = (func, delay) => {
    let timeout;
    const debounced = function(...args) {
      const context = this;
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(context, args), delay);
    };
    debounced.cancel = () => {
      clearTimeout(timeout);
    };
    return debounced;
  };
  var activeChapterId = null;
  var chapterEditorViews2 = /* @__PURE__ */ new Map();
  var currentSourceSelection = { text: "", hasSelection: false, range: null };
  var lastBroadcastedSourceSelectionState = false;
  var totalIframes = 0;
  var iframesReadyCount = 0;
  var viewInitialized = false;
  var activeEditor = null;
  var searchResultHandler = null;
  var searchReplaceResultHandler = null;
  var lastFocusedSourceEditor = null;
  var getActiveEditor = () => activeEditor;
  var getLastFocusedSourceEditor = () => lastFocusedSourceEditor;
  var setActiveEditor = (editorWindow) => {
    activeEditor = editorWindow;
  };
  var setActiveChapterId = (chapterId, callback) => {
    if (chapterId && chapterId !== activeChapterId) {
      activeChapterId = chapterId;
      if (callback) callback(activeChapterId);
    }
  };
  var debouncedContentSave = debounce4(async ({ chapterId, field, value }) => {
    if (field === "target_content") {
      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = value;
      const wordCount = tempDiv.textContent.trim().split(/\s+/).filter(Boolean).length;
      const chapterItem = document.getElementById(`target-chapter-scroll-target-${chapterId}`);
      if (chapterItem) {
        const wordCountEl2 = chapterItem.querySelector(".js-target-word-count");
        if (wordCountEl2) {
          wordCountEl2.textContent = `${wordCount.toLocaleString()} ${t("common.words")}`;
        }
      }
    }
    try {
      await window.api.updateChapterField({ chapterId, field, value });
    } catch (error) {
      console.error(`[SAVE] Error saving ${field} for chapter ${chapterId}:`, error);
      window.showAlert(`Could not save ${field} changes.`);
    }
  }, 1e3);
  var debouncedSaveScroll = debounce4((bookId, sourceEl, targetEl) => {
    if (!bookId || !sourceEl || !targetEl || viewInitialized === false) return;
    const positions = {
      source: sourceEl.scrollTop,
      target: targetEl.scrollTop
    };
    localStorage.setItem(`scroll-position-${bookId}`, JSON.stringify(positions));
  }, 500);
  function restoreScrollPositions(bookId, sourceEl, targetEl) {
    const saved = localStorage.getItem(`scroll-position-${bookId}`);
    if (saved) {
      try {
        const positions = JSON.parse(saved);
        if (positions.source) sourceEl.scrollTop = positions.source;
        if (positions.target) targetEl.scrollTop = positions.target;
        return true;
      } catch (e) {
        console.error("Failed to parse saved scroll positions:", e);
        localStorage.removeItem(`scroll-position-${bookId}`);
      }
    }
    return false;
  }
  async function renderSourceChapterContent(chapterId, rawHtml) {
    const chapterItem = document.getElementById(`source-chapter-scroll-target-${chapterId}`);
    if (!chapterItem) return;
    const contentContainer = chapterItem.querySelector(".source-content-readonly");
    if (!contentContainer) return;
    contentContainer.innerHTML = processSourceContentForMarkers(rawHtml || "");
  }
  async function toggleSourceEditMode(chapterId, isEditing) {
    const chapterItem = document.getElementById(`source-chapter-scroll-target-${chapterId}`);
    if (!chapterItem) return;
    const actionsContainer = chapterItem.querySelector(".js-source-actions");
    const contentContainer = chapterItem.querySelector(".source-content-readonly");
    if (!actionsContainer || !contentContainer) return;
    const editBtn = actionsContainer.querySelector(".js-edit-source-btn");
    const saveBtn = actionsContainer.querySelector(".js-save-source-btn");
    const cancelBtn = actionsContainer.querySelector(".js-cancel-source-btn");
    editBtn.classList.toggle("hidden", isEditing);
    saveBtn.classList.toggle("hidden", !isEditing);
    cancelBtn.classList.toggle("hidden", !isEditing);
    if (isEditing) {
      const rawContent = await window.api.getRawChapterContent({ chapterId, field: "source_content" });
      contentContainer.contentEditable = true;
      contentContainer.innerHTML = rawContent || "";
      contentContainer.focus();
    } else {
      contentContainer.contentEditable = false;
      const rawContent = await window.api.getRawChapterContent({ chapterId, field: "source_content" });
      await renderSourceChapterContent(chapterId, rawContent);
    }
  }
  async function saveSourceChanges(chapterId) {
    const chapterItem = document.getElementById(`source-chapter-scroll-target-${chapterId}`);
    if (!chapterItem) return;
    const contentContainer = chapterItem.querySelector(".source-content-readonly");
    const newContent = contentContainer.innerHTML;
    try {
      await window.api.updateChapterField({ chapterId, field: "source_content", value: newContent });
      const tempDiv = document.createElement("div");
      tempDiv.innerHTML = newContent;
      const wordCount = tempDiv.textContent.trim().split(/\s+/).filter(Boolean).length;
      const wordCountEl2 = chapterItem.querySelector(".js-source-word-count");
      if (wordCountEl2) {
        wordCountEl2.textContent = `${wordCount.toLocaleString()} ${t("common.words")}`;
      }
      await toggleSourceEditMode(chapterId, false);
      await renderSourceChapterContent(chapterId, newContent);
    } catch (error) {
      console.error(`[SAVE] Error saving source content for chapter ${chapterId}:`, error);
      window.showAlert("Could not save source content changes.");
    }
  }
  function synchronizeMarkers(rawSourceHtml, rawTargetHtml) {
    const markerRegex = /(\[\[#(\d+)\]\])|(\{\{#(\d+)\}\})/g;
    let sourceHtml = rawSourceHtml || "";
    const targetHtml = rawTargetHtml || "";
    const getMarkerNumbers = (html) => {
      const numbers = /* @__PURE__ */ new Set();
      if (!html) return numbers;
      const matches = [...html.matchAll(markerRegex)];
      matches.forEach((match) => numbers.add(parseInt(match[2] || match[4], 10)));
      return numbers;
    };
    const sourceMarkerNumbers = getMarkerNumbers(sourceHtml);
    if (sourceMarkerNumbers.size === 0) {
      return { cleanedSourceContent: sourceHtml, wasModified: false };
    }
    const targetMarkerNumbers = getMarkerNumbers(targetHtml);
    let wasModified = false;
    sourceMarkerNumbers.forEach((number) => {
      if (!targetMarkerNumbers.has(number)) {
        const openingMarkerRegex = new RegExp(`\\[\\[#${number}\\]\\]\\s*`, "g");
        const closingMarkerRegex = new RegExp(`\\{\\{#${number}\\}\\}\\s*`, "g");
        const originalSourceHtml = sourceHtml;
        sourceHtml = sourceHtml.replace(openingMarkerRegex, "").replace(closingMarkerRegex, "");
        if (sourceHtml !== originalSourceHtml) {
          wasModified = true;
        }
      }
    });
    return { cleanedSourceContent: sourceHtml, wasModified };
  }
  async function renderManuscript(bookData) {
    const sourceContainer = document.getElementById("js-source-column-container");
    const targetContainer = document.getElementById("js-target-column-container");
    const sourceFragment = document.createDocumentFragment();
    const targetFragment = document.createDocumentFragment();
    totalIframes = 0;
    const [
      sourceChapterTpl,
      targetChapterTpl
    ] = await Promise.all([
      window.api.getTemplate("editor/source-chapter"),
      window.api.getTemplate("editor/target-chapter")
    ]);
    const tempDiv = document.createElement("div");
    if (!bookData.chapters || bookData.chapters.length === 0) {
      const noChaptersMessage = document.createElement("p");
      noChaptersMessage.className = "px-8 py-6 text-base-content/60";
      noChaptersMessage.textContent = t("editor.noChaptersInBook");
      sourceFragment.appendChild(noChaptersMessage);
      targetFragment.appendChild(noChaptersMessage.cloneNode(true));
    } else {
      for (const chapter of bookData.chapters) {
        const { cleanedSourceContent, wasModified } = synchronizeMarkers(chapter.source_content, chapter.target_content);
        if (wasModified) {
          window.api.updateChapterField({ chapterId: chapter.id, field: "source_content", value: cleanedSourceContent });
        }
        const rawSourceContent = cleanedSourceContent || "";
        const finalSourceContent = processSourceContentForMarkers(rawSourceContent);
        const sourceHtml = sourceChapterTpl.replace(/{{id}}/g, chapter.id).replace(/{{title}}/g, chapter.title).replace(/{{source_word_count}}/g, chapter.source_word_count.toLocaleString()).replace("{{source_content}}", finalSourceContent);
        tempDiv.innerHTML = sourceHtml.trim();
        const sourceChapterWrapper = tempDiv.firstChild;
        sourceFragment.appendChild(sourceChapterWrapper);
        const targetHtml = targetChapterTpl.replace(/{{id}}/g, chapter.id).replace(/{{title}}/g, chapter.title).replace(/{{target_word_count}}/g, chapter.target_word_count.toLocaleString());
        tempDiv.innerHTML = targetHtml.trim();
        const targetChapterWrapper = tempDiv.firstChild;
        targetFragment.appendChild(targetChapterWrapper);
        const iframe = targetChapterWrapper.querySelector("iframe");
        totalIframes++;
        const viewInfo = { iframe, isReady: false, initialContent: chapter.target_content || "", initialResizeComplete: false };
        chapterEditorViews2.set(chapter.id.toString(), viewInfo);
        iframe.addEventListener("load", () => {
          viewInfo.contentWindow = iframe.contentWindow;
          viewInfo.isReady = true;
          const settings = getTypographySettings();
          const styleProps = generateTypographyStyleProperties(settings);
          iframe.contentWindow.postMessage({ type: "updateTypography", payload: { styleProps, settings } }, window.location.origin);
          iframe.contentWindow.postMessage({
            type: "init",
            payload: {
              initialHtml: viewInfo.initialContent,
              isEditable: true,
              chapterId: chapter.id,
              field: "target_content",
              theme: document.documentElement.getAttribute("data-theme") || "light",
              i18n: {}
            }
          }, window.location.origin);
        });
      }
    }
    sourceContainer.innerHTML = "";
    targetContainer.innerHTML = "";
    sourceContainer.appendChild(sourceFragment);
    targetContainer.appendChild(targetFragment);
    sourceContainer.querySelectorAll("a.translation-marker-link").forEach((link) => {
      if (link.textContent.trim() === "") {
        link.remove();
      }
    });
    applyTranslationsTo(sourceContainer);
    applyTranslationsTo(targetContainer);
  }
  function populateNavDropdown(bookData) {
    const navDropdown = document.getElementById("js-chapter-nav-dropdown");
    navDropdown.innerHTML = "";
    if (bookData.chapters?.length > 0) {
      bookData.chapters.forEach((chapter) => {
        const option = new Option(chapter.title?.trim() ? ` ${chapter.title}` : `${chapter.chapter_order}. ...`, chapter.id);
        navDropdown.appendChild(option);
      });
    }
    navDropdown.addEventListener("change", () => scrollToChapter(navDropdown.value, setActiveChapterId));
  }
  function initializeView(bookId, bookData, initialChapterId) {
    if (viewInitialized) return;
    viewInitialized = true;
    const sourceContainer = document.getElementById("js-source-column-container");
    const targetContainer = document.getElementById("js-target-column-container");
    setTimeout(() => {
      if (!restoreScrollPositions(bookId, sourceContainer, targetContainer)) {
        const chapterToLoad = initialChapterId || bookData.chapters[0]?.id;
        if (chapterToLoad) {
          document.getElementById("js-chapter-nav-dropdown").value = chapterToLoad;
          setTimeout(() => scrollToChapter(chapterToLoad, setActiveChapterId), 50);
        }
      }
    }, 500);
  }
  document.addEventListener("DOMContentLoaded", async () => {
    await loadModals([
      "prompt-editor-modal",
      "alert-modal",
      "typography-settings-modal",
      "dictionary-modal",
      "confirmation-modal",
      "input-modal"
    ], "modal-placeholders");
    await initI18n();
    document.getElementById("js-refresh-page-btn")?.addEventListener("click", () => window.location.reload());
    const pathParts = window.location.pathname.split("/").filter(Boolean);
    const bookId = pathParts[0] === "chapter-editor" ? pathParts[1] : null;
    const initialChapterId = pathParts[0] === "chapter-editor" ? pathParts[2] : null;
    window.showAlert = (message, title = t("common.error")) => {
      const modal = document.getElementById("alert-modal");
      if (modal) {
        modal.querySelector("#alert-modal-title").textContent = title;
        modal.querySelector("#alert-modal-content").textContent = message;
        modal.showModal();
      } else {
        alert(message);
      }
    };
    if (!bookId) {
      document.body.innerHTML = `<p class="text-error p-8">${t("editor.errorProjectMissing")}</p>`;
      return;
    }
    document.body.dataset.bookId = bookId;
    try {
      const bookData = await window.api.getFullManuscript(bookId);
      if (!bookData || !bookData.title) throw new Error("Failed to load project data.");
      document.title = t("editor.translating", { title: bookData.title });
      document.getElementById("js-book-title").textContent = bookData.title;
      const totalTargetWords = bookData.chapters?.reduce((sum, ch) => sum + ch.target_word_count, 0) || 0;
      document.getElementById("js-total-word-count").textContent = `${totalTargetWords.toLocaleString()} ${t("common.words")}`;
      const sourceContainer = document.getElementById("js-source-column-container");
      const targetContainer = document.getElementById("js-target-column-container");
      if (!bookData.chapters || bookData.chapters.length === 0) {
        const noContentHtml = `<div class="p-8 text-center text-base-content/70"><p>${t("editor.noProjectContent")}</p><p class="text-sm mt-2">${t("editor.noProjectContentHelp")}</p></div>`;
        sourceContainer.innerHTML = noContentHtml;
        targetContainer.innerHTML = noContentHtml;
        document.getElementById("js-chapter-nav-dropdown").disabled = true;
        return;
      }
      await renderManuscript(bookData);
      populateNavDropdown(bookData);
      setupTopToolbar({
        isChapterEditor: true,
        getActiveChapterId: () => activeChapterId,
        getChapterViews: (chapterId) => chapterEditorViews2.get(chapterId.toString()),
        onOpenDictionary: () => handleOpenDictionaryWithSelection(getActiveEditor(), currentSourceSelection)
      });
      setupPromptEditor();
      setupTypographySettings({
        buttonId: "typography-settings-btn",
        modalId: "typography-settings-modal",
        formId: "typography-settings-form",
        applyCallback: (styleProps, settings) => {
          chapterEditorViews2.forEach((viewInfo) => {
            if (viewInfo.isReady) {
              viewInfo.contentWindow.postMessage({ type: "updateTypography", payload: { styleProps, settings } }, window.location.origin);
            }
          });
          document.querySelectorAll(".js-source-column").forEach((col) => {
            Object.entries(styleProps).forEach(([prop, value]) => col.style.setProperty(prop, value));
          });
        }
      });
      setupIntersectionObserver(setActiveChapterId);
      setupSpellcheckDropdown();
      const searchAPI = setupSearch(chapterEditorViews2, (handler) => {
        searchResultHandler = handler;
      });
      const searchReplaceAPI = setupSearchAndReplace(chapterEditorViews2, (handler) => {
        searchReplaceResultHandler = handler;
      });
      setupShortcuts({
        searchAPI,
        searchReplaceAPI,
        getActiveEditor,
        getLastFocusedSourceEditor,
        chapterEditorViews: chapterEditorViews2
      });
      initDictionaryModal(bookId);
      document.body.addEventListener("dictionary:find-replace", (event) => {
        const { find, replace } = event.detail;
        if (searchReplaceAPI && searchReplaceAPI.openWithValues) {
          searchReplaceAPI.openWithValues(find, replace);
        }
      });
      if (totalIframes === 0) {
        initializeView(bookId, bookData, initialChapterId);
      }
      document.getElementById("js-open-chat-btn")?.addEventListener("click", () => window.api.openChatWindow(bookId));
      const tmStatusEl = document.getElementById("js-tm-status");
      const codexStatusEl = document.getElementById("js-codex-status");
      window.api.codex.startGeneration(bookId);
      window.api.codex.onUpdate((event, { statusKey, progress, total }) => {
        if (codexStatusEl) {
          const message = t(statusKey, { progress, total });
          codexStatusEl.textContent = `Codex: ${message}`;
        }
      });
      window.api.codex.onFinished((event, { status, message }) => {
        if (codexStatusEl) {
          let statusMessage = "";
          if (status === "complete") {
            statusMessage = t("editor.codex.status.complete");
          } else if (status === "error") {
            statusMessage = t("editor.codex.status.error", { message });
          } else if (status === "cancelled") {
            statusMessage = t("editor.codex.status.cancelled");
          }
          codexStatusEl.textContent = `Codex: ${statusMessage}`;
          setTimeout(() => {
            if (status === "complete") {
              codexStatusEl.textContent = `Codex: ${t("editor.codex.status.ready")}`;
            }
          }, 5e3);
        }
      });
      let isTmUpdateRunning = false;
      const runTmUpdate = async () => {
        if (isTmUpdateRunning) {
          console.log("TM update is already in progress. Skipping.");
          return;
        }
        isTmUpdateRunning = true;
        try {
          await window.api.translationMemoryGenerateInBackground(bookId);
        } catch (error) {
          tmStatusEl.textContent = t("editor.translationMemory.status.error", { message: error.message });
          isTmUpdateRunning = false;
        }
      };
      window.api.onTranslationMemoryProgressUpdate((update) => {
        if (update.error) {
          tmStatusEl.textContent = t("editor.translationMemory.status.error", { message: update.message });
          isTmUpdateRunning = false;
        } else if (update.finished) {
          if (update.processedCount > 0) {
            tmStatusEl.textContent = t("editor.translationMemory.status.complete", { count: update.processedCount });
          } else {
            tmStatusEl.textContent = t("editor.translationMemory.status.complete_none");
          }
          isTmUpdateRunning = false;
          setTimeout(() => {
            tmStatusEl.textContent = "";
          }, 5e3);
        } else if (update.processed !== void 0 && update.total !== void 0) {
          tmStatusEl.textContent = t("editor.translationMemory.status.generating", { processed: update.processed, total: update.total });
        } else {
          tmStatusEl.textContent = update.message;
        }
      });
      runTmUpdate();
      setInterval(runTmUpdate, 6e4 * 5);
      sourceContainer.addEventListener("scroll", () => debouncedSaveScroll(bookId, sourceContainer, targetContainer));
      targetContainer.addEventListener("scroll", () => debouncedSaveScroll(bookId, sourceContainer, targetContainer));
      const debouncedSelectionUiHandler = debounce4(() => {
        const selection = window.getSelection();
        let isSourceSelectionHandled = false;
        if (selection?.rangeCount > 0 && !selection.isCollapsed) {
          const range = selection.getRangeAt(0);
          const checkNode = range.startContainer.nodeType === Node.TEXT_NODE ? range.startContainer.parentElement : range.startContainer;
          if (checkNode.closest(".source-content-readonly")) {
            const selectedText = selection.toString().trim();
            if (selectedText.length > 0) {
              isSourceSelectionHandled = true;
              const wordCount = selectedText.split(/\s+/).filter(Boolean).length;
              document.getElementById("js-word-count").textContent = t("editor.wordsSelectedSource", { count: wordCount });
            }
          }
        }
        if (!isSourceSelectionHandled) updateToolbarState(null);
      }, 100);
      document.addEventListener("selectionchange", () => {
        debouncedSelectionUiHandler();
        const selection = window.getSelection();
        let hasSourceSelection = false;
        let selectedText = "";
        let selectionRange = null;
        if (selection?.rangeCount > 0 && !selection.isCollapsed) {
          const range = selection.getRangeAt(0);
          const checkNode = range.startContainer.nodeType === Node.TEXT_NODE ? range.startContainer.parentElement : range.startContainer;
          if (checkNode.closest(".source-content-readonly")) {
            selectedText = selection.toString().trim();
            if (selectedText.length > 0) {
              hasSourceSelection = true;
              selectionRange = range.cloneRange();
            }
          }
        }
        currentSourceSelection = { text: selectedText, hasSelection: hasSourceSelection, range: selectionRange };
        if (hasSourceSelection !== lastBroadcastedSourceSelectionState) {
          lastBroadcastedSourceSelectionState = hasSourceSelection;
          chapterEditorViews2.forEach((viewInfo) => {
            if (viewInfo.isReady) {
              viewInfo.contentWindow.postMessage({ type: "sourceSelectionChanged", payload: { hasSelection: hasSourceSelection } }, window.location.origin);
            }
          });
        }
      });
      sourceContainer.addEventListener("click", async (event) => {
        const target = event.target;
        const syncBtn = target.closest(".js-sync-scroll-btn");
        if (syncBtn) {
          event.preventDefault();
          syncChapterScroll(syncBtn.dataset.chapterId, syncBtn.dataset.direction);
          return;
        }
        const markerLink = target.closest("a.translation-marker-link");
        if (markerLink) {
          event.preventDefault();
          const chapterId = markerLink.closest(".manuscript-chapter-item").dataset.chapterId;
          const markerId = markerLink.dataset.markerId;
          const markerType = markerLink.dataset.markerType;
          if (markerId && chapterId && markerType) {
            scrollToTargetMarker(chapterId, markerId, markerType, chapterEditorViews2);
          }
          return;
        }
        const chapterItem = target.closest(".manuscript-chapter-item");
        if (chapterItem) {
          const chapterId = chapterItem.dataset.chapterId;
          if (target.closest(".js-edit-source-btn")) await toggleSourceEditMode(chapterId, true);
          if (target.closest(".js-save-source-btn")) await saveSourceChanges(chapterId);
          if (target.closest(".js-cancel-source-btn")) await toggleSourceEditMode(chapterId, false);
        }
        const chapterActionBtn = target.closest(".js-chapter-action");
        if (chapterActionBtn) {
          const { action, chapterId } = chapterActionBtn.dataset;
          if (action === "rename") {
            const currentTitle = chapterActionBtn.closest(".js-source-actions").parentElement.querySelector("h3").textContent.split("(")[0].trim();
            const newTitle = await showInputModal(t("editor.renameChapter"), t("editor.promptNewChapterTitle"), currentTitle);
            if (newTitle) {
              await window.api.renameChapter({ chapterId, newTitle });
              window.location.reload();
            }
          } else if (action === "delete") {
            if (await showConfirmationModal(t("editor.deleteChapter"), t("editor.confirmDeleteChapter"))) {
              await window.api.deleteChapter({ chapterId });
              window.location.reload();
            }
          } else if (action === "insert-above" || action === "insert-below") {
            await window.api.insertChapter({ chapterId, direction: action.replace("insert-", "") });
            window.location.reload();
          }
        }
        const contentDiv = target.closest(".source-content-readonly");
        if (contentDiv) {
          sourceContainer.querySelectorAll(".source-content-readonly").forEach((div) => {
            if (div !== contentDiv) {
              div.contentEditable = false;
            }
          });
          contentDiv.contentEditable = true;
        }
      });
      sourceContainer.addEventListener("focusin", (event) => {
        const contentDiv = event.target.closest(".source-content-readonly");
        if (contentDiv) {
          lastFocusedSourceEditor = contentDiv;
        }
      });
      sourceContainer.addEventListener("beforeinput", (event) => {
        const contentDiv = event.target.closest(".source-content-readonly");
        if (contentDiv) {
          const chapterItem = contentDiv.closest(".manuscript-chapter-item");
          const saveBtn = chapterItem?.querySelector(".js-save-source-btn");
          if (saveBtn && saveBtn.classList.contains("hidden")) {
            event.preventDefault();
          }
        }
      });
      targetContainer.addEventListener("click", (event) => {
        const syncBtn = event.target.closest(".js-sync-scroll-btn");
        if (syncBtn) {
          event.preventDefault();
          syncChapterScroll(syncBtn.dataset.chapterId, syncBtn.dataset.direction);
        }
      });
      window.api?.onManuscriptScrollToChapter((event, chapterId) => {
        if (chapterId) {
          scrollToChapter(chapterId, setActiveChapterId);
          document.getElementById("js-chapter-nav-dropdown").value = chapterId;
        }
      });
      window.addEventListener("message", (event) => {
        const isFromKnownIframe = Array.from(chapterEditorViews2.values()).some((view) => view.iframe.contentWindow === event.source);
        if (!isFromKnownIframe) return;
        const { type, payload } = event.data;
        const sourceWindow = event.source;
        switch (type) {
          case "editorFocused":
            setActiveEditor(sourceWindow);
            setActiveContentWindow(sourceWindow);
            updateToolbarState(payload.state);
            setActiveChapterId(payload.chapterId, (id) => {
              document.getElementById("js-chapter-nav-dropdown").value = id;
            });
            break;
          case "editorBlurred":
            setTimeout(() => {
              if (document.activeElement.closest("#top-toolbar") || document.activeElement.closest(".modal")) return;
              setActiveEditor(null);
              setActiveContentWindow(null);
              updateToolbarState(null);
            }, 100);
            break;
          case "stateUpdate":
            if (getActiveEditor() === sourceWindow) updateToolbarState(payload.state);
            break;
          case "contentChanged":
            debouncedContentSave(payload);
            break;
          case "resize": {
            const viewInfo = Array.from(chapterEditorViews2.values()).find((v) => v.contentWindow === sourceWindow);
            if (viewInfo) {
              viewInfo.iframe.style.height = `${payload.height}px`;
              if (!viewInfo.initialResizeComplete) {
                viewInfo.initialResizeComplete = true;
                iframesReadyCount++;
                if (iframesReadyCount >= totalIframes && !viewInitialized) {
                  initializeView(bookId, bookData, initialChapterId);
                }
              }
            }
            break;
          }
          case "scrollToCoordinates": {
            const viewInfo = Array.from(chapterEditorViews2.values()).find((v) => v.contentWindow === event.source);
            if (viewInfo) {
              const iframeRect = viewInfo.iframe.getBoundingClientRect();
              const containerRect = targetContainer.getBoundingClientRect();
              const scrollPosition = targetContainer.scrollTop + (iframeRect.top - containerRect.top) + payload.top - 100;
              targetContainer.scrollTo({ top: scrollPosition, behavior: "smooth" });
            }
            break;
          }
          case "search:results":
            if (searchResultHandler) searchResultHandler(payload);
            break;
          case "search-replace:results":
          case "search-replace:replaced":
          case "search-replace:replacedAll":
            if (searchReplaceResultHandler) searchReplaceResultHandler(type, payload);
            break;
          case "markerClicked": {
            const { markerId, markerType } = payload;
            scrollToSourceMarker(markerId, markerType);
            break;
          }
          case "requestTranslation": {
            const viewInfo = Array.from(chapterEditorViews2.values()).find((v) => v.contentWindow === sourceWindow);
            if (!viewInfo || !currentSourceSelection.hasSelection) return;
            (async () => {
              const bookData2 = await window.api.getOneBook(bookId);
              let settings = {};
              try {
                settings = bookData2.translate_settings ? JSON.parse(bookData2.translate_settings) : {};
              } catch (e) {
                console.error("Error parsing translate_settings JSON", e);
              }
              const context = {
                selectedText: currentSourceSelection.text,
                sourceSelectionRange: currentSourceSelection.range,
                languageForPrompt: bookData2.source_language || "English",
                targetLanguage: bookData2.target_language || "English",
                activeEditorView: sourceWindow,
                editorInterface: createIframeEditorInterface(sourceWindow),
                chapterId: viewInfo.iframe.dataset.chapterId,
                bookId,
                insertionPoint: { from: payload.from, to: payload.to }
              };
              openPromptEditor(context, "translate", settings);
            })();
            break;
          }
          case "shortcut:find":
            if (searchReplaceAPI.isHidden()) {
              searchAPI.toggle(true);
            }
            break;
          case "shortcut:find-replace":
            searchReplaceAPI.toggle(true);
            break;
          case "shortcut:focus-source":
            if (lastFocusedSourceEditor) {
              lastFocusedSourceEditor.focus();
            } else {
              const sourceContainer2 = document.getElementById("js-source-column-container");
              const firstEditor = sourceContainer2.querySelector(".source-content-readonly");
              if (firstEditor) {
                firstEditor.focus();
              } else {
                sourceContainer2.focus({ preventScroll: true });
              }
            }
            break;
          case "shortcut:focus-target":
            sourceWindow.postMessage({ type: "focusEditor" }, window.location.origin);
            break;
        }
      });
    } catch (error) {
      console.error("Failed to load manuscript data:", error);
      document.body.innerHTML = `<p class="p-8 text-error">${t("editor.errorLoadManuscript", { message: error.message })}</p>`;
    }
  });
})();
