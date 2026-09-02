/**
 * SearchManager
 *
 * Manages search functionality with debouncing and URL state management
 *
 * @module SearchManager
 * @author ProudNerds
 * @version 1.0.0
 */

/**
 * Manages search input and filtering
 *
 * @class
 */
class SearchManager {
  /**
   * Debounce delay in milliseconds for search input
   * @type {number}
   */
  static DEBOUNCE_DELAY = 400

  /**
   * Default minimum characters for search
   * @type {number}
   */
  static DEFAULT_MIN_CHARS = 3

  /**
   * Creates an instance of SearchManager
   *
   * @constructor
   * @param {HTMLFormElement} searchForm - The search form element
   * @param {Function} onSearchCallback - Callback function when search is triggered
   */
  constructor(searchForm, onSearchCallback) {
    this.searchForm = searchForm
    this.searchInput = searchForm.querySelector('.pn-category-filter__search-input')
    this.onSearchCallback = onSearchCallback
    this.debounceTimer = null
    this.minChars = parseInt(searchForm.dataset.searchMinChars || SearchManager.DEFAULT_MIN_CHARS, 10)

    this.init()
  }

  /**
   * Initializes the search manager
   *
   * @returns {void}
   */
  init() {
    this.attachEventListeners()
    this.applySearchFromUrl()
  }

  /**
   * Attaches event listeners
   *
   * @returns {void}
   */
  attachEventListeners() {
    // Handle form submission
    this.searchForm.addEventListener('submit', (e) => {
      e.preventDefault()
      this.handleSearch()
    })

    // Handle input with debouncing (also catches native browser clear button)
    this.searchInput.addEventListener('input', () => {
      this.debouncedSearch()
    })

    // Handle native search clear (triggered by browser's X button)
    this.searchInput.addEventListener('search', () => {
      // This event fires when the user clicks the native clear button
      this.handleSearch()
    })

    // Handle Enter key
    this.searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault()
        this.clearDebounce()
        this.handleSearch()
      }
    })
  }

  /**
   * Handles search execution
   *
   * @returns {void}
   */
  handleSearch() {
    const searchTerm = this.getSearchTerm()

    // Only trigger callback if search term meets minimum length requirement
    // Or if search term is empty (to show all results again)
    if (searchTerm.length === 0 || searchTerm.length >= this.minChars) {
      this.onSearchCallback(searchTerm)
    }
    // If below minimum, don't trigger search (user is still typing)
  }

  /**
   * Debounced search handler
   *
   * @returns {void}
   */
  debouncedSearch() {
    this.clearDebounce()
    this.debounceTimer = setTimeout(() => {
      this.handleSearch()
    }, SearchManager.DEBOUNCE_DELAY)
  }

  /**
   * Clears the debounce timer
   *
   * @returns {void}
   */
  clearDebounce() {
    if (this.debounceTimer) {
      clearTimeout(this.debounceTimer)
      this.debounceTimer = null
    }
  }

  /**
   * Gets the current search term (trimmed)
   *
   * @returns {string}
   */
  getSearchTerm() {
    return this.searchInput.value.trim()
  }

  /**
   * Sets the search term in the input field
   *
   * @param {string} searchTerm - The search term to set
   * @returns {void}
   */
  setSearchTerm(searchTerm) {
    this.searchInput.value = searchTerm || ''
  }

  /**
   * Applies search term from URL parameters on page load
   *
   * @returns {void}
   */
  applySearchFromUrl() {
    const urlParams = new URLSearchParams(window.location.search)
    const searchTerm = urlParams.get('tx_pncategoryfilter_categoryfilterlist[search]')

    if (searchTerm) {
      this.setSearchTerm(searchTerm)
    }
  }

  /**
   * Gets the AJAX URL from the form data attribute
   *
   * @returns {string|null}
   */
  getAjaxUrl() {
    return this.searchForm.dataset.ajaxUrl || null
  }

  /**
   * Gets the search fields configuration
   *
   * @returns {string}
   */
  getSearchFields() {
    return this.searchForm.dataset.searchFields || 'title,description'
  }
}

export default SearchManager
