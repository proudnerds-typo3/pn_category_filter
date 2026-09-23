/* Generated from Resources/Private/Assets/ with esbuild, do not edit. See CONTRIBUTING.md. */
(() => {
  // Resources/Private/Assets/Scripts/Utils/ContentLoader.js
  var ContentLoader = class _ContentLoader {
    static #FADE_DURATION = 100;
    /**
     * No fade when the visitor asked for reduced motion
     * @returns {number}
     */
    static get fadeDuration() {
      return window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 0 : _ContentLoader.#FADE_DURATION;
    }
    /**
     * @param {string} url - The URL to fetch content from
     * @param {HTMLElement} targetElement - The element to insert the content into
     * @param {boolean} append - Whether to append the content or replace it
     * @param {boolean} showSpinner - Whether to show a spinner while loading the content
     * @param {string} method - The HTTP method to use (GET or POST)
     * @param {Object} [data] - The data to send with the request (for POST method)
     * @returns {Promise<void>}
     */
    static async loadContent(url, targetElement, append = false, showSpinner = true, method = "GET", data = null) {
      try {
        const spinner = document.createElement("div");
        spinner.className = "pn-category-filter__spinner";
        const contentPromise = this.fetchContent(url, method, data);
        contentPromise.catch(() => {
        });
        if (!append) {
          if (showSpinner) {
            await this.fadeOutContent(targetElement);
            this.clearContent(targetElement, spinner);
          } else {
            this.clearContent(targetElement, null);
          }
        } else {
          if (showSpinner) {
            targetElement.appendChild(spinner);
          }
        }
        const content = await contentPromise;
        if (append) {
          targetElement.insertAdjacentHTML("beforeend", content);
        } else {
          await this.replaceContent(targetElement, content);
        }
        const contentLoaderUpdatedEvent = new CustomEvent("ContentLoaderUpdated", {
          detail: { targetElement }
        });
        document.dispatchEvent(contentLoaderUpdatedEvent);
        if (showSpinner) {
          this.removeSpinner(targetElement, spinner);
        }
      } catch (error) {
        console.error("Error loading content:", error);
        this.showError(targetElement);
      }
    }
    /**
     * Fade out the content of the target element
     * @param {HTMLElement} targetElement - The element to fade out
     * @returns {Promise<void>}
     */
    static async fadeOutContent(targetElement) {
      const originalHeight = targetElement.offsetHeight;
      targetElement.style.height = `${originalHeight}px`;
      if (this.fadeDuration === 0) return;
      targetElement.classList.add("fade-out");
      await new Promise((resolve) => setTimeout(resolve, this.fadeDuration));
    }
    /**
     * Clear the content of the target element and show the spinner
     * @param {HTMLElement} targetElement - The element to clear
     * @param {HTMLElement} spinner - The spinner element to show
     */
    static clearContent(targetElement, spinner) {
      targetElement.innerHTML = "";
      if (spinner) {
        targetElement.appendChild(spinner);
      }
      targetElement.classList.remove("fade-out");
    }
    /**
     * Fetch content from the given URL using the specified method
     * @param {string} url - The URL to fetch content from
     * @param {string} method - The HTTP method to use (GET or POST)
     * @param {Object} [data] - The data to send with the request (for POST method)
     * @returns {Promise<string>} - The fetched content as a string
     */
    static async fetchContent(url, method = "GET", data = null) {
      const options = {
        method,
        headers: {
          "Content-Type": "application/x-www-form-urlencoded"
        }
      };
      if (method === "POST" && data) {
        options.body = new URLSearchParams(data);
      }
      const response = await fetch(url, options);
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return await response.text();
    }
    /**
     * Replace the content of the target element and fade in the new content
     * @param {HTMLElement} targetElement - The element to replace content in
     * @param {string} content - The new content to insert
     * @returns {Promise<void>}
     */
    static async replaceContent(targetElement, content) {
      targetElement.innerHTML = content;
      if (this.fadeDuration > 0) {
        targetElement.classList.add("fade-in");
        await new Promise((resolve) => setTimeout(resolve, this.fadeDuration));
        targetElement.classList.remove("fade-in");
      }
      targetElement.style.height = "";
    }
    /**
     * Remove the spinner from the target element
     * @param {HTMLElement} targetElement - The element to remove the spinner from
     * @param {HTMLElement} spinner - The spinner element to remove
     */
    static removeSpinner(targetElement, spinner) {
      if (spinner.parentNode === targetElement) {
        targetElement.removeChild(spinner);
      }
    }
    /**
     * Show an error message in the target element
     * @param {HTMLElement} targetElement - The element to show the error message in
     */
    static showError(targetElement) {
      const errorMessage = `<p class="mt-4 p-2 pn-social-ads-loading__error">Fout bij het laden van de inhoud. Als deze fout blijft optreden, neem dan svp contact op.</p>`;
      targetElement.innerHTML = errorMessage;
    }
  };
  var ContentLoader_default = ContentLoader;

  // Resources/Private/Assets/Scripts/Utils/LoadingManager.js
  var LoadingManager = class _LoadingManager {
    constructor() {
      if (!_LoadingManager.instance) {
        this.globalLoading = false;
        this.actionLoading = {};
        _LoadingManager.instance = this;
      }
      return _LoadingManager.instance;
    }
    setLoading(loading, action = null) {
      if (action) {
        this.actionLoading[action] = loading;
      } else {
        this.globalLoading = loading;
      }
    }
    getLoading(action = null) {
      if (action) {
        return !!this.actionLoading[action];
      }
      return this.globalLoading;
    }
  };
  var instance = new LoadingManager();
  var LoadingManager_default = instance;

  // Resources/Private/Assets/Scripts/Components/SortingManager.js
  var SortingManager = class {
    // The default sort order is pre-checked in the markup; only a visitor's own choice may reach
    // the server as sorting, or boost and relevance ordering would be switched off.
    #userHasSorted = new URLSearchParams(window.location.search).has("tx_pncategoryfilter_categoryfilterlist[sortField]");
    /**
     * Creates an instance of SortingManager
     *
     * @constructor
     * @param {HTMLElement} sortContainer - The sort container element
     * @param {Function} onSortChange - Callback when sort changes
     */
    constructor(sortContainer, onSortChange) {
      this.sortContainer = sortContainer;
      this.onSortChange = onSortChange;
      this.checkboxes = sortContainer.querySelectorAll(".pn-category-filter__sort-checkbox");
      this.selects = sortContainer.querySelectorAll(".pn-category-filter__sort-select");
      this.init();
    }
    /**
     * Initializes the manager
     *
     * @returns {void}
     */
    init() {
      this.attachCheckboxListeners();
      this.attachSelectListeners();
      this.initializeVisibility();
    }
    /**
     * Initializes select visibility based on checkbox state
     *
     * @returns {void}
     */
    initializeVisibility() {
      this.checkboxes.forEach((checkbox) => {
        const sortField = checkbox.dataset.sortField;
        if (!checkbox.checked) {
          this.hideSelectForField(sortField);
        }
      });
    }
    /**
     * Attaches event listeners to checkboxes
     *
     * @returns {void}
     */
    attachCheckboxListeners() {
      this.checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", (e) => {
          this.handleCheckboxChange(e);
        });
      });
    }
    /**
     * Attaches event listeners to select dropdowns
     *
     * @returns {void}
     */
    attachSelectListeners() {
      this.selects.forEach((select) => {
        select.addEventListener("change", (e) => {
          this.handleSelectChange(e);
        });
      });
    }
    /**
     * Handles checkbox change - only one checkbox can be selected at a time
     *
     * @param {Event} event - The change event
     * @returns {void}
     */
    handleCheckboxChange(event) {
      this.#userHasSorted = true;
      const checkbox = event.target;
      const sortField = checkbox.dataset.sortField;
      const isChecked = checkbox.checked;
      if (isChecked) {
        this.checkboxes.forEach((cb) => {
          if (cb !== checkbox) {
            cb.checked = false;
            this.hideSelectForField(cb.dataset.sortField);
          }
        });
        this.showSelectForField(sortField);
        const select = this.getSelectForField(sortField);
        const sortOrder = select ? select.value : "desc";
        this.triggerSortChange(sortField, sortOrder);
      } else {
        this.hideSelectForField(sortField);
        this.triggerSortChange("", "none");
      }
    }
    /**
     * Handles select dropdown change
     *
     * @param {Event} event - The change event
     * @returns {void}
     */
    handleSelectChange(event) {
      this.#userHasSorted = true;
      const select = event.target;
      const sortField = select.dataset.sortField;
      const sortOrder = select.value;
      const checkbox = this.getCheckboxForField(sortField);
      if (checkbox && !checkbox.checked) {
        checkbox.checked = true;
      }
      this.triggerSortChange(sortField, sortOrder);
    }
    /**
     * Shows select dropdown for a field
     *
     * @param {string} sortField - The field name
     * @returns {void}
     */
    showSelectForField(sortField) {
      const select = this.getSelectForField(sortField);
      if (select) {
        select.style.display = "";
        select.removeAttribute("style");
      }
    }
    /**
     * Hides select dropdown for a field
     *
     * @param {string} sortField - The field name
     * @returns {void}
     */
    hideSelectForField(sortField) {
      const select = this.getSelectForField(sortField);
      if (select) {
        select.style.display = "none";
      }
    }
    /**
     * Gets select element for a field
     *
     * @param {string} sortField - The field name
     * @returns {HTMLSelectElement|null}
     */
    getSelectForField(sortField) {
      return this.sortContainer.querySelector(`.pn-category-filter__sort-select[data-sort-field="${sortField}"]`);
    }
    /**
     * Gets checkbox element for a field
     *
     * @param {string} sortField - The field name
     * @returns {HTMLInputElement|null}
     */
    getCheckboxForField(sortField) {
      return this.sortContainer.querySelector(`.pn-category-filter__sort-checkbox[data-sort-field="${sortField}"]`);
    }
    /**
     * Triggers sort change callback
     *
     * @param {string} sortField - The field to sort on
     * @param {string} sortOrder - The sort order (asc/desc/none)
     * @returns {void}
     */
    triggerSortChange(sortField, sortOrder) {
      if (typeof this.onSortChange === "function") {
        this.onSortChange({ sortField, sortOrder });
      }
    }
    /**
     * Whether the visitor chose a sort order, as opposed to the pre-checked default
     *
     * @returns {boolean}
     */
    hasUserSorted() {
      return this.#userHasSorted;
    }
    /**
     * Carries the visitor's choice over to a new instance after the sort UI was re-rendered
     *
     * @param {boolean} userHasSorted
     * @returns {void}
     */
    setUserHasSorted(userHasSorted) {
      this.#userHasSorted = userHasSorted;
    }
    /**
     * Gets current sorting state
     *
     * @returns {{sortField: string, sortOrder: string}}
     */
    getCurrentSorting() {
      const checkedCheckbox = Array.from(this.checkboxes).find((cb) => cb.checked);
      if (!checkedCheckbox) {
        return { sortField: "", sortOrder: "none" };
      }
      const sortField = checkedCheckbox.dataset.sortField;
      const select = this.getSelectForField(sortField);
      const sortOrder = select ? select.value : "desc";
      return { sortField, sortOrder };
    }
    /**
     * Sets sorting state (e.g., after AJAX update)
     *
     * @param {string} sortField - The field to sort on
     * @param {string} sortOrder - The sort order (asc/desc/none)
     * @returns {void}
     */
    setSorting(sortField, sortOrder) {
      this.checkboxes.forEach((cb) => {
        cb.checked = false;
        this.hideSelectForField(cb.dataset.sortField);
      });
      if (!sortField || sortOrder === "none") {
        this.#userHasSorted = false;
        return;
      }
      const checkbox = this.getCheckboxForField(sortField);
      if (checkbox) {
        checkbox.checked = true;
        this.showSelectForField(sortField);
      }
      const select = this.getSelectForField(sortField);
      if (select) {
        select.value = sortOrder;
      }
    }
  };
  var SortingManager_default = SortingManager;

  // Resources/Private/Assets/Scripts/Components/SearchManager.js
  var SearchManager = class _SearchManager {
    /**
     * Debounce delay in milliseconds for search input
     * @type {number}
     */
    static DEBOUNCE_DELAY = 250;
    /**
     * Default minimum characters for search
     * @type {number}
     */
    static DEFAULT_MIN_CHARS = 3;
    /**
     * Creates an instance of SearchManager
     *
     * @constructor
     * @param {HTMLFormElement} searchForm - The search form element
     * @param {Function} onSearchCallback - Callback function when search is triggered
     */
    constructor(searchForm, onSearchCallback) {
      this.searchForm = searchForm;
      this.searchInput = searchForm.querySelector(".pn-category-filter__search-input");
      this.onSearchCallback = onSearchCallback;
      this.debounceTimer = null;
      this.minChars = parseInt(searchForm.dataset.searchMinChars || _SearchManager.DEFAULT_MIN_CHARS, 10);
      this.init();
    }
    /**
     * Initializes the search manager
     *
     * @returns {void}
     */
    init() {
      this.attachEventListeners();
      this.applySearchFromUrl();
    }
    /**
     * Attaches event listeners
     *
     * @returns {void}
     */
    attachEventListeners() {
      this.searchForm.addEventListener("submit", (e) => {
        e.preventDefault();
        this.handleSearch();
      });
      this.searchInput.addEventListener("input", () => {
        this.debouncedSearch();
      });
      this.searchInput.addEventListener("search", () => {
        this.handleSearch();
      });
      this.searchInput.addEventListener("keydown", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          this.clearDebounce();
          this.handleSearch();
        }
      });
    }
    /**
     * Handles search execution
     *
     * @returns {void}
     */
    handleSearch() {
      const searchTerm = this.getSearchTerm();
      if (searchTerm.length === 0 || searchTerm.length >= this.minChars) {
        this.onSearchCallback(searchTerm);
      }
    }
    /**
     * Debounced search handler
     *
     * @returns {void}
     */
    debouncedSearch() {
      this.clearDebounce();
      this.debounceTimer = setTimeout(() => {
        this.handleSearch();
      }, _SearchManager.DEBOUNCE_DELAY);
    }
    /**
     * Clears the debounce timer
     *
     * @returns {void}
     */
    clearDebounce() {
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = null;
      }
    }
    /**
     * Gets the current search term (trimmed)
     *
     * @returns {string}
     */
    getSearchTerm() {
      return this.searchInput.value.trim();
    }
    /**
     * Sets the search term in the input field
     *
     * @param {string} searchTerm - The search term to set
     * @returns {void}
     */
    setSearchTerm(searchTerm) {
      this.searchInput.value = searchTerm || "";
    }
    /**
     * Applies search term from URL parameters on page load
     *
     * @returns {void}
     */
    applySearchFromUrl() {
      const urlParams = new URLSearchParams(window.location.search);
      const searchTerm = urlParams.get("tx_pncategoryfilter_categoryfilterlist[search]");
      if (searchTerm) {
        this.setSearchTerm(searchTerm);
      }
    }
    /**
     * Gets the AJAX URL from the form data attribute
     *
     * @returns {string|null}
     */
    getAjaxUrl() {
      return this.searchForm.dataset.ajaxUrl || null;
    }
    /**
     * Gets the search fields configuration
     *
     * @returns {string}
     */
    getSearchFields() {
      return this.searchForm.dataset.searchFields || "title,description";
    }
  };
  var SearchManager_default = SearchManager;

  // Resources/Private/Assets/Scripts/Components/SuggestionManager.js
  var SuggestionManager = class {
    #input;
    #listbox;
    #status;
    #options = [];
    #activeIndex = -1;
    // After a choice the list stays closed until the visitor types again
    #waitForInput = false;
    #minChars;
    #onSelectTitle;
    #onSelectCategory;
    /**
     * @param {HTMLFormElement} searchForm - The search form containing the listbox
     * @param {{onSelectTitle: Function, onSelectCategory: Function}} callbacks
     */
    constructor(searchForm, { onSelectTitle, onSelectCategory }) {
      this.#input = searchForm.querySelector(".pn-category-filter__search-input");
      this.#listbox = searchForm.querySelector("[data-search-suggestions]");
      this.#status = searchForm.querySelector("[data-search-suggestions-status]");
      this.#onSelectTitle = onSelectTitle;
      this.#onSelectCategory = onSelectCategory;
      this.#minChars = parseInt(searchForm.dataset.searchMinChars || "3", 10);
      if (this.#input && this.#listbox) {
        this.#init(searchForm);
      }
    }
    /**
     * @param {HTMLFormElement} searchForm
     * @returns {boolean}
     */
    static isEnabled(searchForm) {
      return Boolean(searchForm?.querySelector("[data-search-suggestions]"));
    }
    #init(searchForm) {
      this.#input.setAttribute("role", "combobox");
      this.#input.setAttribute("aria-autocomplete", "list");
      this.#input.setAttribute("aria-expanded", "false");
      this.#input.setAttribute("aria-controls", this.#listbox.id);
      searchForm.addEventListener("keydown", (event) => this.#handleKeydown(event), true);
      this.#input.addEventListener("blur", () => this.close());
      this.#input.addEventListener("input", () => {
        this.#waitForInput = false;
        if (this.#input.value.trim().length < this.#minChars) {
          this.update(null);
        }
      });
      this.#listbox.addEventListener("mousedown", (event) => event.preventDefault());
      this.#listbox.addEventListener("click", (event) => {
        const option = event.target.closest('[role="option"]');
        if (option) {
          this.#select(this.#options.indexOf(option));
        }
      });
    }
    /**
     * Shows the suggestions of the latest results update; opens only while the visitor is typing.
     *
     * @param {{titles: string[], categories: {uid: number, title: string, count: number}[]}|null} data
     * @returns {void}
     */
    update(data) {
      if (!this.#listbox) return;
      const titles = data?.titles ?? [];
      const categories = data?.categories ?? [];
      this.#listbox.replaceChildren();
      this.#options = [];
      this.#activeIndex = -1;
      const categoryLabel = this.#listbox.dataset.categoryLabel || "Category";
      categories.forEach((category) => {
        this.#addOption("category", String(category.uid), `${categoryLabel}: ${category.title} (${category.count})`);
      });
      titles.forEach((title) => this.#addOption("title", title, title));
      if (this.#options.length === 0 || this.#waitForInput || document.activeElement !== this.#input) {
        this.close();
        if (this.#status && this.#options.length === 0) this.#status.textContent = "";
        return;
      }
      this.#open();
    }
    #open() {
      this.#listbox.hidden = false;
      this.#input.setAttribute("aria-expanded", "true");
      this.#input.removeAttribute("aria-activedescendant");
      if (this.#status) {
        this.#status.textContent = (this.#listbox.dataset.countLabel || "%s").replace("%s", String(this.#options.length));
      }
    }
    /**
     * @returns {void}
     */
    close() {
      if (!this.#listbox) return;
      this.#listbox.hidden = true;
      this.#input.setAttribute("aria-expanded", "false");
      this.#input.removeAttribute("aria-activedescendant");
      this.#setActive(-1);
    }
    #addOption(type, value, label) {
      const option = document.createElement("li");
      option.id = `${this.#listbox.id}-option-${this.#options.length}`;
      option.className = `pn-category-filter__suggestion pn-category-filter__suggestion--${type}`;
      option.setAttribute("role", "option");
      option.setAttribute("aria-selected", "false");
      option.dataset.suggestionType = type;
      option.dataset.suggestionValue = value;
      option.textContent = label;
      this.#listbox.appendChild(option);
      this.#options.push(option);
    }
    #isOpen() {
      return !this.#listbox.hidden && this.#options.length > 0;
    }
    #handleKeydown(event) {
      if (event.target !== this.#input) return;
      if (!this.#isOpen()) {
        if (event.key === "ArrowDown" && this.#options.length > 0) {
          event.preventDefault();
          this.#open();
          this.#setActive(0);
        }
        return;
      }
      switch (event.key) {
        case "ArrowDown":
          event.preventDefault();
          this.#setActive((this.#activeIndex + 1) % this.#options.length);
          break;
        case "ArrowUp":
          event.preventDefault();
          this.#setActive(this.#activeIndex <= 0 ? this.#options.length - 1 : this.#activeIndex - 1);
          break;
        case "Enter":
          if (this.#activeIndex >= 0) {
            event.preventDefault();
            event.stopPropagation();
            this.#select(this.#activeIndex);
          } else {
            this.close();
          }
          break;
        case "Escape":
          event.preventDefault();
          event.stopPropagation();
          this.close();
          break;
        case "Tab":
          this.close();
          break;
      }
    }
    #setActive(index) {
      this.#options.forEach((option, i) => option.setAttribute("aria-selected", String(i === index)));
      this.#activeIndex = index;
      if (index >= 0) {
        this.#input.setAttribute("aria-activedescendant", this.#options[index].id);
        this.#options[index].scrollIntoView({ block: "nearest" });
      } else {
        this.#input.removeAttribute("aria-activedescendant");
      }
    }
    #select(index) {
      const option = this.#options[index];
      if (!option) return;
      const { suggestionType, suggestionValue } = option.dataset;
      this.close();
      this.#waitForInput = true;
      if (suggestionType === "category") {
        this.#onSelectCategory(suggestionValue);
      } else {
        this.#input.value = suggestionValue;
        this.#onSelectTitle(suggestionValue);
      }
    }
  };
  var SuggestionManager_default = SuggestionManager;

  // Resources/Private/Assets/Scripts/Components/CategoryFilterManager.js
  var CategoryFilterManager = class {
    /**
     * Creates an instance of CategoryFilterManager
     *
     * @constructor
     * @param {HTMLElement} filterContainer - The filter container element
     */
    constructor(filterContainer) {
      this.filterContainer = filterContainer;
      this.resultsContainer = document.querySelector(".pn-category-filter__results");
      this.checkboxes = filterContainer.querySelectorAll(".pn-category-filter__category-checkbox");
      this.isResetting = false;
      this.pendingReload = false;
      this.filterRoot = filterContainer.closest(".pn-category-filter") ?? filterContainer.parentElement;
      const filterRoot = this.filterRoot;
      const sortContainer = filterContainer.querySelector(".pn-category-filter__sort") ?? filterRoot?.querySelector(".pn-category-filter__sort");
      if (sortContainer) {
        this.sortingManager = new SortingManager_default(sortContainer, (sortData) => {
          this.handleSortChange(sortData);
        });
      }
      const searchForm = filterContainer.querySelector(".pn-category-filter__search-form") ?? filterRoot?.querySelector(".pn-category-filter__search-form");
      if (searchForm) {
        this.searchManager = new SearchManager_default(searchForm, (searchTerm) => {
          this.handleSearchChange(searchTerm);
        });
        this.#createSuggestionManager(searchForm);
      }
      this.init();
    }
    /**
     * Initializes the manager
     *
     * @returns {void}
     */
    init() {
      this.attachEventListeners();
      this.applyFiltersFromUrl();
      this.attachContentUpdateListener();
      this.attachResetButtonListener();
      this.attachPaginationListener();
      this.attachMobileToggleListener();
      if (this.filterRoot.classList.contains("pn-category-filter--horizontal")) {
        this.#attachAccordionListener();
      }
    }
    /**
     * Attaches event listeners to checkboxes
     *
     * @returns {void}
     */
    attachEventListeners() {
      this.checkboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => this.handleFilterChange());
      });
    }
    /**
     * Attaches event listener to reset button using event delegation
     *
     * @returns {void}
     */
    attachResetButtonListener() {
      this.filterRoot.addEventListener("click", (e) => {
        const resetButton = e.target.closest('[data-action="reset-filters"]');
        if (resetButton) {
          e.preventDefault();
          this.handleReset();
        }
      });
    }
    /**
     * Handles reset button click - clears all filters and reloads results
     *
     * @returns {Promise<void>}
     */
    async handleReset() {
      this.checkboxes.forEach((checkbox) => {
        checkbox.checked = false;
      });
      if (this.sortingManager) {
        this.sortingManager.setSorting("", "none");
      }
      if (this.searchManager) {
        this.searchManager.setSearchTerm("");
      }
      const cleanUrl = window.location.pathname;
      window.history.pushState({}, "", cleanUrl);
      this.isResetting = true;
      await this.queueResultsReload();
    }
    /**
     * Attaches listener for content updates to handle pushState
     *
     * @returns {void}
     */
    attachContentUpdateListener() {
      document.addEventListener("ContentLoaderUpdated", (event) => {
        if (event.detail.targetElement === this.resultsContainer) {
          if (!this.isResetting) {
            const pushStateElement = this.resultsContainer.querySelector("[data-pushstate-url]");
            if (pushStateElement && pushStateElement.dataset.pushstateUrl) {
              const decodedUrl = decodeURIComponent(pushStateElement.dataset.pushstateUrl);
              window.history.pushState({}, "", decodedUrl);
            }
          }
          const newSearchForm = this.resultsContainer.querySelector(".pn-category-filter__search-form");
          if (newSearchForm && this.searchManager) {
            const previousTerm = this.searchManager.getSearchTerm();
            this.searchManager = new SearchManager_default(newSearchForm, (searchTerm) => {
              this.handleSearchChange(searchTerm);
            });
            if (previousTerm) {
              this.searchManager.setSearchTerm(previousTerm);
            }
            this.#createSuggestionManager(newSearchForm);
          }
          this.suggestionManager?.update(this.#readSuggestions());
          const newSortContainer = this.resultsContainer.querySelector(".pn-category-filter__sort");
          if (newSortContainer) {
            const userHasSorted = this.sortingManager?.hasUserSorted() ?? false;
            this.sortingManager = new SortingManager_default(newSortContainer, (sortData) => {
              this.handleSortChange(sortData);
            });
            if (userHasSorted) {
              this.sortingManager.setUserHasSorted(true);
            }
          }
          this.updateCategoryAmounts();
          this.applyResultCounts();
        }
      });
    }
    /**
     * @param {HTMLFormElement} searchForm
     * @returns {void}
     */
    #createSuggestionManager(searchForm) {
      this.suggestionManager = SuggestionManager_default.isEnabled(searchForm) ? new SuggestionManager_default(searchForm, {
        onSelectTitle: (title) => {
          this.searchManager?.clearDebounce();
          this.handleSearchChange(title);
        },
        onSelectCategory: (categoryUid) => this.#selectCategory(categoryUid)
      }) : null;
    }
    /**
     * @returns {Object|null} Suggestions shipped with the latest results update
     */
    #readSuggestions() {
      const element = this.resultsContainer.querySelector("[data-suggestions-json]");
      if (!element) return null;
      try {
        return JSON.parse(element.textContent);
      } catch {
        return null;
      }
    }
    /**
     * @param {string} categoryUid
     * @returns {void}
     */
    #selectCategory(categoryUid) {
      const checkbox = Array.from(this.checkboxes).find((cb) => cb.value === categoryUid);
      if (checkbox && !checkbox.checked) {
        checkbox.checked = true;
        this.handleFilterChange();
      }
    }
    /**
     * Handles filter change event
     *
     * @returns {Promise<void>}
     */
    async handleFilterChange() {
      await this.queueResultsReload();
    }
    /**
     * Handles sort change event from SortingManager
     *
     * @param {Object} _sortData - Sort data (unused, kept for interface consistency)
     * @returns {Promise<void>}
     */
    async handleSortChange(_sortData) {
      await this.queueResultsReload();
    }
    /**
     * Handles search change event from SearchManager
     *
     * @param {string} _searchTerm - Search term (unused, kept for interface consistency)
     * @returns {Promise<void>}
     */
    async handleSearchChange(_searchTerm) {
      await this.queueResultsReload();
    }
    /**
     * Reloads the results, serialising concurrent interactions instead of dropping them.
     *
     * A single load takes a few hundred milliseconds (fade out, fetch, fade in) and
     * loadResults() reads the checkbox state at request-build time. Interactions arriving
     * during a running load used to be discarded, leaving the results, the total count and
     * the count badges on an older filter state than the checkboxes. They now raise a pending
     * flag instead, and the running load does one final pass with the then-current state.
     * The flag is a boolean, so a burst of clicks collapses into a single trailing request
     * that carries all of them.
     *
     * @returns {Promise<void>}
     */
    async queueResultsReload() {
      const action = "categoryFilter";
      if (LoadingManager_default.getLoading(action)) {
        this.pendingReload = true;
        return;
      }
      LoadingManager_default.setLoading(true, action);
      try {
        do {
          this.pendingReload = false;
          try {
            await this.loadResults();
          } catch (error) {
            console.error("Error loading filtered results:", error);
          }
          this.updateCategoryAmounts();
        } while (this.pendingReload);
      } finally {
        this.pendingReload = false;
        this.isResetting = false;
        LoadingManager_default.setLoading(false, action);
      }
    }
    /**
     * Loads filtered results via AJAX
     *
     * @returns {Promise<void>}
     */
    async loadResults() {
      const firstCheckbox = this.checkboxes[0];
      if (!firstCheckbox || !firstCheckbox.dataset.ajaxUrl) {
        console.error("No checkbox or AJAX URL found");
        return;
      }
      const baseUrl = firstCheckbox.dataset.ajaxUrl;
      const checkedBoxes = Array.from(this.checkboxes).filter((cb) => cb.checked);
      const formData = {};
      const urlObj = new URL(baseUrl, window.location.origin);
      urlObj.searchParams.forEach((value, key) => {
        if (!key.includes("[selectedCategories]")) {
          formData[key] = value;
        }
      });
      const selectedCategories = checkedBoxes.map((cb) => cb.value).sort((a, b) => parseInt(a, 10) - parseInt(b, 10));
      if (selectedCategories.length > 0) {
        selectedCategories.forEach((categoryId, index) => {
          formData[`tx_pncategoryfilter_categoryfilterlist[selectedCategories][${index}]`] = categoryId;
        });
      }
      if (this.sortingManager?.hasUserSorted()) {
        const { sortField, sortOrder } = this.sortingManager.getCurrentSorting();
        if (sortField && sortOrder !== "none") {
          formData["tx_pncategoryfilter_categoryfilterlist[sorting]"] = sortOrder;
          formData["tx_pncategoryfilter_categoryfilterlist[sortField]"] = sortField;
        }
      }
      if (this.searchManager) {
        const searchTerm = this.searchManager.getSearchTerm();
        if (searchTerm) {
          formData["tx_pncategoryfilter_categoryfilterlist[search]"] = searchTerm;
        }
      }
      await ContentLoader_default.loadContent(
        urlObj.pathname + urlObj.search,
        this.resultsContainer,
        false,
        // append
        true,
        // showSpinner
        "POST",
        formData
      );
    }
    /**
     * Gets selected category UIDs (sorted numerically)
     *
     * @returns {number[]}
     */
    getSelectedCategories() {
      const selected = [];
      this.checkboxes.forEach((checkbox) => {
        if (checkbox.checked) {
          selected.push(parseInt(checkbox.value, 10));
        }
      });
      return selected.sort((a, b) => a - b);
    }
    /**
     * Applies filters from URL parameters on page load
     *
     * @returns {void}
     */
    applyFiltersFromUrl() {
      const urlParams = new URLSearchParams(window.location.search);
      const selectedCategories = [];
      urlParams.forEach((value, key) => {
        if (key.includes("tx_pncategoryfilter_categoryfilterlist[selectedCategories]")) {
          selectedCategories.push(parseInt(value, 10));
        }
      });
      if (selectedCategories.length > 0) {
        this.checkboxes.forEach((checkbox) => {
          const categoryId = parseInt(checkbox.value, 10);
          checkbox.checked = selectedCategories.includes(categoryId);
        });
      }
      this.updateCategoryAmounts();
    }
    /**
     * Updates the amount badges for parent categories
     * Shows the badge only when one or more child filters are active
     * Displays the count of active child filters
     *
     * @returns {void}
     */
    updateCategoryAmounts() {
      const parentCategories = this.filterContainer.querySelectorAll('[data-has-children="true"]');
      parentCategories.forEach((parentItem) => {
        const amountBadge = parentItem.querySelector(".pn-category-filter__amount");
        if (!amountBadge) {
          return;
        }
        const childCheckboxes = parentItem.querySelectorAll(".pn-category-filter__category-checkbox");
        const activeChildCount = Array.from(childCheckboxes).filter((cb) => cb.checked).length;
        if (activeChildCount > 0) {
          amountBadge.textContent = activeChildCount;
          amountBadge.classList.remove("hidden");
        } else {
          amountBadge.classList.add("hidden");
        }
      });
    }
    /**
     * Applies the server-provided result counts to the filter menu badges after an AJAX swap.
     *
     * The filter menu lives outside the AJAX-swapped results container, so its result-count
     * badges are patched in place instead of re-rendered (preserving open/scroll DOM state).
     * Reads the authoritative categoryUid => count map (all nodes, zeros included) from the
     * JSON script emitted in AjaxLoadResults.html, patches every [data-result-count] badge by
     * its closest [data-category-uid] ancestor, and for leaf nodes mirrors the server-render
     * rule: a 0 count greys the node (--empty) and disables its checkbox; any other count
     * re-enables it.
     *
     * Independent of updateCategoryAmounts() (the __amount active-filter badge): both badge
     * types are toggled separately. No-op when the map is absent (initial page load already
     * renders correct counts) or when result counts are disabled (no badges exist then).
     *
     * @returns {void}
     */
    applyResultCounts() {
      if (!this.resultsContainer) {
        return;
      }
      const countsScript = this.resultsContainer.querySelector("script[data-category-counts]");
      if (!countsScript) {
        return;
      }
      let counts;
      try {
        counts = JSON.parse(countsScript.textContent);
      } catch (error) {
        console.error("Invalid category counts JSON:", error);
        return;
      }
      const badges = this.filterContainer.querySelectorAll("[data-result-count]");
      badges.forEach((badge) => {
        const node = badge.closest("[data-category-uid]");
        if (!node) {
          return;
        }
        const uid = node.dataset.categoryUid;
        if (!(uid in counts)) {
          return;
        }
        const count = counts[uid];
        badge.textContent = count;
        const srPhrase = badge.nextElementSibling;
        if (srPhrase && srPhrase.hasAttribute("data-result-count-sr")) {
          const srValue = srPhrase.querySelector("[data-result-count-value]");
          if (srValue) {
            srValue.textContent = count;
          }
        }
        if (node.dataset.hasChildren === "true") {
          return;
        }
        this.#toggleLeafEmptyState(node, count === 0);
      });
    }
    /**
     * Mirrors the server-render rule for a leaf node: toggles the --empty modifier on the node
     * wrapper and the disabled state on its checkbox. The modifier is derived from the node's
     * own BEM block class so it works for both top-level items and nested children.
     *
     * @param {HTMLElement} node - The leaf wrapper carrying data-category-uid
     * @param {boolean} isEmpty - Whether this leaf has zero matching results
     * @returns {void}
     * @private
     */
    #toggleLeafEmptyState(node, isEmpty) {
      const emptyModifier = node.classList.contains("pn-category-filter__category-child") ? "pn-category-filter__category-child--empty" : "pn-category-filter__category-item--empty";
      node.classList.toggle(emptyModifier, isEmpty);
      const checkbox = node.querySelector(".pn-category-filter__category-checkbox");
      if (checkbox) {
        checkbox.disabled = isEmpty && !checkbox.checked;
      }
    }
    /**
     * Attaches event listener for pagination links
     * Intercepts clicks on pagination links with data-page-link attribute to load via AJAX
     *
     * @returns {void}
     */
    attachPaginationListener() {
      document.addEventListener("click", async (event) => {
        const paginationLink = event.target.closest("[data-page-link]");
        if (paginationLink && this.resultsContainer.contains(paginationLink)) {
          event.preventDefault();
          const action = "categoryFilter";
          if (LoadingManager_default.getLoading(action)) {
            return;
          }
          LoadingManager_default.setLoading(true, action);
          try {
            const url = paginationLink.getAttribute("href");
            await ContentLoader_default.loadContent(
              url,
              this.resultsContainer,
              false,
              // append
              true,
              // showSpinner
              "GET"
            );
            this.resultsContainer.scrollIntoView({ behavior: "smooth", block: "start" });
          } catch (error) {
            console.error("Error loading paginated results:", error);
          } finally {
            LoadingManager_default.setLoading(false, action);
          }
          if (this.pendingReload) {
            await this.queueResultsReload();
          }
        }
      });
    }
    /**
     * Attaches event listener for the mobile toggle button(s)
     * Toggles the visibility of the target element specified in aria-controls
     * Implements focus trapping and body scroll prevention for WCAG compliance
     * Supports multiple toggle buttons controlling the same target
     *
     * @returns {void}
     */
    attachMobileToggleListener() {
      const toggleButtons = this.filterContainer.querySelectorAll(".pn-category-filter__toggle");
      if (toggleButtons.length === 0) {
        return;
      }
      const targetId = toggleButtons[0].getAttribute("aria-controls");
      if (!targetId) {
        console.warn("Toggle button missing aria-controls attribute");
        return;
      }
      const targetElement = document.getElementById(targetId);
      if (!targetElement) {
        console.warn(`Target element with id "${targetId}" not found`);
        return;
      }
      toggleButtons.forEach((toggleButton) => {
        toggleButton.addEventListener("click", () => {
          const isExpanded = toggleButton.getAttribute("aria-expanded") === "true";
          if (isExpanded) {
            this.#closeMobileOverlay(toggleButton, targetElement);
          } else {
            this.#openMobileOverlay(toggleButton, targetElement);
          }
        });
      });
      targetElement.addEventListener(
        "keydown",
        (e) => this.#handleMobileOverlayKeydown(e, toggleButtons[0], targetElement)
      );
      document.addEventListener("click", (e) => {
        const isOverlayOpen = targetElement.classList.contains("is-open");
        const clickedOutside = !targetElement.contains(e.target) && !Array.from(toggleButtons).some((btn) => btn.contains(e.target));
        if (isOverlayOpen && clickedOutside) {
          this.#closeMobileOverlay(toggleButtons[0], targetElement);
        }
      });
    }
    /**
     * Opens the mobile filter overlay with focus management and scroll prevention
     * Updates all toggle buttons to reflect expanded state
     *
     * @private
     */
    #openMobileOverlay(toggleButton, targetElement) {
      const toggleButtons = this.filterContainer.querySelectorAll(".pn-category-filter__toggle");
      toggleButtons.forEach((btn) => btn.setAttribute("aria-expanded", "true"));
      targetElement.classList.add("is-open");
      this.filterContainer.classList.add("is-open");
      document.body.classList.add("fixed");
      const focusableElements = this.#getFocusableElementsInMenu();
      if (focusableElements.length > 0) {
        focusableElements[0].focus();
      }
    }
    /**
     * Closes the mobile filter overlay and restores scroll
     * Updates all toggle buttons to reflect collapsed state
     *
     * @private
     */
    #closeMobileOverlay(toggleButton, targetElement) {
      const toggleButtons = this.filterContainer.querySelectorAll(".pn-category-filter__toggle");
      toggleButtons.forEach((btn) => btn.setAttribute("aria-expanded", "false"));
      targetElement.classList.remove("is-open");
      this.filterContainer.classList.remove("is-open");
      document.body.classList.remove("fixed");
      toggleButton.focus();
    }
    /**
     * Handles keyboard events in the mobile overlay
     * Closes on Escape key, traps Tab focus within the menu
     *
     * @private
     */
    #handleMobileOverlayKeydown(event, toggleButton, targetElement) {
      if (event.key === "Escape") {
        this.#closeMobileOverlay(toggleButton, targetElement);
        return;
      }
      if (event.key === "Tab" && !targetElement.classList.contains("is-open")) {
        return;
      }
      if (event.key === "Tab") {
        this.#trapMobileOverlayFocus(event);
      }
    }
    /**
     * Traps focus within the filter menu for WCAG compliance
     *
     * @private
     */
    #trapMobileOverlayFocus(event) {
      const focusableElements = this.#getFocusableElementsInMenu();
      if (focusableElements.length === 0) {
        event.preventDefault();
        return;
      }
      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];
      const activeElement = document.activeElement;
      if (event.shiftKey) {
        if (activeElement === firstElement) {
          event.preventDefault();
          lastElement.focus();
        }
      } else {
        if (activeElement === lastElement) {
          event.preventDefault();
          firstElement.focus();
        }
      }
    }
    /**
     * Gets all focusable elements within the filter menu
     *
     * @returns {HTMLElement[]}
     * @private
     */
    #getFocusableElementsInMenu() {
      const focusableSelector = `
      button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])
    `;
      return Array.from(this.filterContainer.querySelectorAll(focusableSelector));
    }
    /**
     * Closes all other <details> category items when one is opened (accordion behaviour)
     *
     * @private
     */
    #attachAccordionListener() {
      const details = this.filterContainer.querySelectorAll("details.pn-category-filter__category-item");
      document.addEventListener("click", (e) => {
        details.forEach((detail) => {
          if (detail.open && !detail.contains(e.target)) {
            detail.removeAttribute("open");
          }
        });
      });
    }
  };
  var CategoryFilterManager_default = CategoryFilterManager;

  // Resources/Private/Assets/Scripts/PnCategoryFilter.js
  var init = () => {
    const instances = document.querySelectorAll(".pn-category-filter__menu");
    if (!instances.length) return;
    instances.forEach((filterMenu) => {
      new CategoryFilterManager_default(filterMenu);
    });
  };
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
