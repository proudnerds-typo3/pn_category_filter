/**
 * PnCategoryFilter - Self-contained IIFE bundle for tilburg_sportbedrijf_sitepackage
 *
 * This is a manually bundled (no Vite/build tool) version of:
 *   pn_category_filter/Resources/Private/Assets/Scripts/PnCategoryFilter.js
 *
 * Includes: ContentLoader, LoadingManager, SearchManager, SortingManager, CategoryFilterManager
 */
;(function () {
  'use strict'

  // ---------------------------------------------------------------------------
  // ContentLoader
  // ---------------------------------------------------------------------------

  /**
   * @classDesc Ajax helper class: load content from a URL and insert it into a target element
   */
  class ContentLoader {
    /**
     * @param {string} url - The URL to fetch content from
     * @param {HTMLElement} targetElement - The element to insert the content into
     * @param {boolean} append - Whether to append the content or replace it
     * @param {boolean} showSpinner - Whether to show a spinner while loading the content
     * @param {string} method - The HTTP method to use (GET or POST)
     * @param {Object} [data] - The data to send with the request (for POST method)
     * @returns {Promise<void>}
     */
    static async loadContent(url, targetElement, append = false, showSpinner = true, method = 'GET', data = null) {
      try {
        const spinner = document.createElement('div')
        spinner.className = 'pn-category-filter__spinner'

        if (!append) {
          if (showSpinner) {
            await this.fadeOutContent(targetElement)
            this.clearContent(targetElement, spinner)
          } else {
            this.clearContent(targetElement, null)
          }
        } else {
          if (showSpinner) {
            targetElement.appendChild(spinner)
          }
        }

        const content = await this.fetchContent(url, method, data)

        if (append) {
          targetElement.insertAdjacentHTML('beforeend', content)
        } else {
          await this.replaceContent(targetElement, content)
        }

        const contentLoaderUpdatedEvent = new CustomEvent('ContentLoaderUpdated', {
          detail: { targetElement: targetElement },
        })
        document.dispatchEvent(contentLoaderUpdatedEvent)

        if (showSpinner) {
          this.removeSpinner(targetElement, spinner)
        }
      } catch (error) {
        console.error('Error loading content:', error)
        this.showError(targetElement)
      }
    }

    static async fadeOutContent(targetElement) {
      targetElement.classList.add('fade-out')
      const originalHeight = targetElement.offsetHeight
      targetElement.style.height = `${originalHeight}px`
      await new Promise((resolve) => setTimeout(resolve, 150))
    }

    static clearContent(targetElement, spinner) {
      targetElement.innerHTML = ''
      if (spinner) {
        targetElement.appendChild(spinner)
      }
      targetElement.classList.remove('fade-out')
    }

    static async fetchContent(url, method = 'GET', data = null) {
      const options = {
        method,
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
      }

      if (method === 'POST' && data) {
        options.body = new URLSearchParams(data)
      }

      const response = await fetch(url, options)
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }
      return await response.text()
    }

    static async replaceContent(targetElement, content) {
      targetElement.innerHTML = content
      targetElement.classList.add('fade-in')
      await new Promise((resolve) => setTimeout(resolve, 150))
      targetElement.classList.remove('fade-in')
      targetElement.style.height = ''
    }

    static removeSpinner(targetElement, spinner) {
      if (spinner.parentNode === targetElement) {
        targetElement.removeChild(spinner)
      }
    }

    static showError(targetElement) {
      const errorMessage = `<p class="mt-4 p-2 pn-social-ads-loading__error">Fout bij het laden van de inhoud. Als deze fout blijft optreden, neem dan svp contact op.</p>`
      targetElement.innerHTML = errorMessage
    }
  }

  // ---------------------------------------------------------------------------
  // LoadingManager
  // ---------------------------------------------------------------------------

  /**
   * @classDesc Helper class for managing loading state, to prevent multiple load requests.
   */
  class LoadingManager {
    constructor() {
      if (!LoadingManager.instance) {
        this.globalLoading = false
        this.actionLoading = {}
        LoadingManager.instance = this
      }
      return LoadingManager.instance
    }

    setLoading(loading, action = null) {
      if (action) {
        this.actionLoading[action] = loading
      } else {
        this.globalLoading = loading
      }
    }

    getLoading(action = null) {
      if (action) {
        return !!this.actionLoading[action]
      }
      return this.globalLoading
    }
  }

  const loadingManager = new LoadingManager()

  // ---------------------------------------------------------------------------
  // SearchManager
  // ---------------------------------------------------------------------------

  /**
   * Manages search functionality with debouncing and URL state management
   *
   * @class
   */
  class SearchManager {
    static DEBOUNCE_DELAY = 400
    static DEFAULT_MIN_CHARS = 3

    constructor(searchForm, onSearchCallback) {
      this.searchForm = searchForm
      this.searchInput = searchForm.querySelector('.pn-category-filter__search-input')
      this.onSearchCallback = onSearchCallback
      this.debounceTimer = null
      this.minChars = parseInt(searchForm.dataset.searchMinChars || SearchManager.DEFAULT_MIN_CHARS, 10)

      this.init()
    }

    init() {
      this.attachEventListeners()
      this.applySearchFromUrl()
    }

    attachEventListeners() {
      this.searchForm.addEventListener('submit', (e) => {
        e.preventDefault()
        this.handleSearch()
      })

      this.searchInput.addEventListener('input', () => {
        this.debouncedSearch()
      })

      this.searchInput.addEventListener('search', () => {
        this.handleSearch()
      })

      this.searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault()
          this.clearDebounce()
          this.handleSearch()
        }
      })
    }

    handleSearch() {
      const searchTerm = this.getSearchTerm()
      if (searchTerm.length === 0 || searchTerm.length >= this.minChars) {
        this.onSearchCallback(searchTerm)
      }
    }

    debouncedSearch() {
      this.clearDebounce()
      this.debounceTimer = setTimeout(() => {
        this.handleSearch()
      }, SearchManager.DEBOUNCE_DELAY)
    }

    clearDebounce() {
      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer)
        this.debounceTimer = null
      }
    }

    getSearchTerm() {
      return this.searchInput.value.trim()
    }

    setSearchTerm(searchTerm) {
      this.searchInput.value = searchTerm || ''
    }

    applySearchFromUrl() {
      const urlParams = new URLSearchParams(window.location.search)
      const searchTerm = urlParams.get('tx_pncategoryfilter_categoryfilterlist[search]')
      if (searchTerm) {
        this.setSearchTerm(searchTerm)
      }
    }

    getAjaxUrl() {
      return this.searchForm.dataset.ajaxUrl || null
    }

    getSearchFields() {
      return this.searchForm.dataset.searchFields || 'title,description'
    }
  }

  // ---------------------------------------------------------------------------
  // SortingManager
  // ---------------------------------------------------------------------------

  /**
   * Manages sorting options for filtered results with AJAX updates
   *
   * @class
   */
  class SortingManager {
    constructor(sortContainer, onSortChange) {
      this.sortContainer = sortContainer
      this.onSortChange = onSortChange
      this.checkboxes = sortContainer.querySelectorAll('.pn-category-filter__sort-checkbox')
      this.selects = sortContainer.querySelectorAll('.pn-category-filter__sort-select')

      this.init()
    }

    init() {
      this.attachCheckboxListeners()
      this.attachSelectListeners()
      this.initializeVisibility()
    }

    initializeVisibility() {
      this.checkboxes.forEach((checkbox) => {
        const sortField = checkbox.dataset.sortField
        if (!checkbox.checked) {
          this.hideSelectForField(sortField)
        }
      })
    }

    attachCheckboxListeners() {
      this.checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', (e) => {
          this.handleCheckboxChange(e)
        })
      })
    }

    attachSelectListeners() {
      this.selects.forEach((select) => {
        select.addEventListener('change', (e) => {
          this.handleSelectChange(e)
        })
      })
    }

    handleCheckboxChange(event) {
      const checkbox = event.target
      const sortField = checkbox.dataset.sortField
      const isChecked = checkbox.checked

      if (isChecked) {
        this.checkboxes.forEach((cb) => {
          if (cb !== checkbox) {
            cb.checked = false
            this.hideSelectForField(cb.dataset.sortField)
          }
        })

        this.showSelectForField(sortField)

        const select = this.getSelectForField(sortField)
        const sortOrder = select ? select.value : 'desc'

        this.triggerSortChange(sortField, sortOrder)
      } else {
        this.hideSelectForField(sortField)
        this.triggerSortChange('', 'none')
      }
    }

    handleSelectChange(event) {
      const select = event.target
      const sortField = select.dataset.sortField
      const sortOrder = select.value

      const checkbox = this.getCheckboxForField(sortField)
      if (checkbox && !checkbox.checked) {
        checkbox.checked = true
      }

      this.triggerSortChange(sortField, sortOrder)
    }

    showSelectForField(sortField) {
      const select = this.getSelectForField(sortField)
      if (select) {
        select.style.display = ''
        select.removeAttribute('style')
      }
    }

    hideSelectForField(sortField) {
      const select = this.getSelectForField(sortField)
      if (select) {
        select.style.display = 'none'
      }
    }

    getSelectForField(sortField) {
      return this.sortContainer.querySelector(`.pn-category-filter__sort-select[data-sort-field="${sortField}"]`)
    }

    getCheckboxForField(sortField) {
      return this.sortContainer.querySelector(`.pn-category-filter__sort-checkbox[data-sort-field="${sortField}"]`)
    }

    triggerSortChange(sortField, sortOrder) {
      if (typeof this.onSortChange === 'function') {
        this.onSortChange({ sortField, sortOrder })
      }
    }

    getCurrentSorting() {
      const checkedCheckbox = Array.from(this.checkboxes).find((cb) => cb.checked)

      if (!checkedCheckbox) {
        return { sortField: '', sortOrder: 'none' }
      }

      const sortField = checkedCheckbox.dataset.sortField
      const select = this.getSelectForField(sortField)
      const sortOrder = select ? select.value : 'desc'

      return { sortField, sortOrder }
    }

    setSorting(sortField, sortOrder) {
      this.checkboxes.forEach((cb) => {
        cb.checked = false
        this.hideSelectForField(cb.dataset.sortField)
      })

      if (!sortField || sortOrder === 'none') {
        return
      }

      const checkbox = this.getCheckboxForField(sortField)
      if (checkbox) {
        checkbox.checked = true
        this.showSelectForField(sortField)
      }

      const select = this.getSelectForField(sortField)
      if (select) {
        select.value = sortOrder
      }
    }
  }

  // ---------------------------------------------------------------------------
  // CategoryFilterManager
  // ---------------------------------------------------------------------------

  /**
   * Manages category filtering with AJAX updates and URL state management
   *
   * @class
   */
  class CategoryFilterManager {
    constructor(filterContainer) {
      this.filterContainer = filterContainer
      this.resultsContainer = document.querySelector('.pn-category-filter__results')
      this.checkboxes = filterContainer.querySelectorAll('.pn-category-filter__category-checkbox')
      this.isResetting = false
      this.pendingReload = false

      this.filterRoot = filterContainer.closest('.pn-category-filter') ?? filterContainer.parentElement
      const filterRoot = this.filterRoot

      const sortContainer =
        filterContainer.querySelector('.pn-category-filter__sort') ??
        filterRoot?.querySelector('.pn-category-filter__sort')
      if (sortContainer) {
        this.sortingManager = new SortingManager(sortContainer, (sortData) => {
          this.handleSortChange(sortData)
        })
      }

      const searchForm =
        filterContainer.querySelector('.pn-category-filter__search-form') ??
        filterRoot?.querySelector('.pn-category-filter__search-form')
      if (searchForm) {
        this.searchManager = new SearchManager(searchForm, (searchTerm) => {
          this.handleSearchChange(searchTerm)
        })
      }

      this.init()
    }

    init() {
      this.attachEventListeners()
      this.applyFiltersFromUrl()
      this.attachContentUpdateListener()
      this.attachResetButtonListener()
      this.attachPaginationListener()
      this.attachMobileToggleListener()
      if (this.filterRoot.classList.contains('pn-category-filter--horizontal')) {
        this._attachAccordionListener()
      }
    }

    attachEventListeners() {
      this.checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => this.handleFilterChange())
      })
    }

    attachResetButtonListener() {
      this.filterRoot.addEventListener('click', (e) => {
        const resetButton = e.target.closest('[data-action="reset-filters"]')
        if (resetButton) {
          e.preventDefault()
          this.handleReset()
        }
      })
    }

    async handleReset() {
      this.checkboxes.forEach((checkbox) => {
        checkbox.checked = false
      })

      if (this.sortingManager) {
        this.sortingManager.setSorting('', 'none')
      }

      if (this.searchManager) {
        this.searchManager.setSearchTerm('')
      }

      const cleanUrl = window.location.pathname
      window.history.pushState({}, '', cleanUrl)

      this.isResetting = true

      await this.queueResultsReload()
    }

    attachContentUpdateListener() {
      document.addEventListener('ContentLoaderUpdated', (event) => {
        if (event.detail.targetElement === this.resultsContainer) {
          if (!this.isResetting) {
            const pushStateElement = this.resultsContainer.querySelector('[data-pushstate-url]')
            if (pushStateElement && pushStateElement.dataset.pushstateUrl) {
              const decodedUrl = decodeURIComponent(pushStateElement.dataset.pushstateUrl)
              window.history.pushState({}, '', decodedUrl)
            }
          }

          const newSearchForm = this.resultsContainer.querySelector('.pn-category-filter__search-form')
          if (newSearchForm && this.searchManager) {
            const previousTerm = this.searchManager.getSearchTerm()
            this.searchManager = new SearchManager(newSearchForm, (searchTerm) => {
              this.handleSearchChange(searchTerm)
            })
            if (previousTerm) {
              this.searchManager.setSearchTerm(previousTerm)
            }
          }

          const newSortContainer = this.resultsContainer.querySelector('.pn-category-filter__sort')
          if (newSortContainer) {
            this.sortingManager = new SortingManager(newSortContainer, (sortData) => {
              this.handleSortChange(sortData)
            })
          }

          this.updateCategoryAmounts()

          // Patch the result-count badges (and 0-count leaf disabled state) from the
          // server counts map that ships inside the swapped results container.
          this.applyResultCounts()
        }
      })
    }

    async handleFilterChange() {
      await this.queueResultsReload()
    }

    async handleSortChange(_sortData) {
      await this.queueResultsReload()
    }

    async handleSearchChange(_searchTerm) {
      await this.queueResultsReload()
    }

    // Serialises concurrent interactions instead of dropping them: a load takes a few hundred
    // milliseconds and loadResults() reads the checkbox state at request-build time, so a
    // discarded click left the results, the total count and the badges on an older filter
    // state than the checkboxes. Interactions during a load now raise a pending flag and the
    // running load does one final pass with the then-current state. The flag is a boolean, so
    // a burst of clicks collapses into a single trailing request carrying all of them.
    async queueResultsReload() {
      const action = 'categoryFilter'

      if (loadingManager.getLoading(action)) {
        this.pendingReload = true
        return
      }

      loadingManager.setLoading(true, action)

      try {
        do {
          this.pendingReload = false

          try {
            await this.loadResults()
          } catch (error) {
            console.error('Error loading filtered results:', error)
          }

          this.updateCategoryAmounts()
        } while (this.pendingReload)
      } finally {
        this.pendingReload = false
        // isResetting is scoped to this run: every pass must skip the pushState so the clean
        // URL set by handleReset() survives a reset that queued behind a load.
        this.isResetting = false
        loadingManager.setLoading(false, action)
      }
    }

    async loadResults() {
      const firstCheckbox = this.checkboxes[0]

      if (!firstCheckbox || !firstCheckbox.dataset.ajaxUrl) {
        console.error('No checkbox or AJAX URL found')
        return
      }

      const baseUrl = firstCheckbox.dataset.ajaxUrl
      const checkedBoxes = Array.from(this.checkboxes).filter((cb) => cb.checked)

      const formData = {}

      const urlObj = new URL(baseUrl, window.location.origin)
      urlObj.searchParams.forEach((value, key) => {
        if (!key.includes('[selectedCategories]')) {
          formData[key] = value
        }
      })

      const selectedCategories = checkedBoxes.map((cb) => cb.value).sort((a, b) => parseInt(a, 10) - parseInt(b, 10))

      if (selectedCategories.length > 0) {
        selectedCategories.forEach((categoryId, index) => {
          formData[`tx_pncategoryfilter_categoryfilterlist[selectedCategories][${index}]`] = categoryId
        })
      }

      if (this.sortingManager) {
        const { sortField, sortOrder } = this.sortingManager.getCurrentSorting()
        if (sortField && sortOrder !== 'none') {
          formData['tx_pncategoryfilter_categoryfilterlist[sorting]'] = sortOrder
          formData['tx_pncategoryfilter_categoryfilterlist[sortField]'] = sortField
        }
      }

      if (this.searchManager) {
        const searchTerm = this.searchManager.getSearchTerm()
        if (searchTerm) {
          formData['tx_pncategoryfilter_categoryfilterlist[search]'] = searchTerm
        }
      }

      await ContentLoader.loadContent(
        urlObj.pathname + urlObj.search,
        this.resultsContainer,
        false,
        true,
        'POST',
        formData
      )
    }

    getSelectedCategories() {
      const selected = []
      this.checkboxes.forEach((checkbox) => {
        if (checkbox.checked) {
          selected.push(parseInt(checkbox.value, 10))
        }
      })
      return selected.sort((a, b) => a - b)
    }

    applyFiltersFromUrl() {
      const urlParams = new URLSearchParams(window.location.search)
      const selectedCategories = []

      urlParams.forEach((value, key) => {
        if (key.includes('tx_pncategoryfilter_categoryfilterlist[selectedCategories]')) {
          selectedCategories.push(parseInt(value, 10))
        }
      })

      if (selectedCategories.length > 0) {
        this.checkboxes.forEach((checkbox) => {
          const categoryId = parseInt(checkbox.value, 10)
          checkbox.checked = selectedCategories.includes(categoryId)
        })
      }

      this.updateCategoryAmounts()
    }

    updateCategoryAmounts() {
      const parentCategories = this.filterContainer.querySelectorAll('[data-has-children="true"]')

      parentCategories.forEach((parentItem) => {
        const amountBadge = parentItem.querySelector('.pn-category-filter__amount')

        if (!amountBadge) {
          return
        }

        const childCheckboxes = parentItem.querySelectorAll('.pn-category-filter__category-checkbox')
        const activeChildCount = Array.from(childCheckboxes).filter((cb) => cb.checked).length

        if (activeChildCount > 0) {
          amountBadge.textContent = activeChildCount
          amountBadge.classList.remove('hidden')
        } else {
          amountBadge.classList.add('hidden')
        }
      })
    }

    applyResultCounts() {
      if (!this.resultsContainer) {
        return
      }

      const countsScript = this.resultsContainer.querySelector('script[data-category-counts]')
      if (!countsScript) {
        return
      }

      let counts
      try {
        counts = JSON.parse(countsScript.textContent)
      } catch (error) {
        console.error('Invalid category counts JSON:', error)
        return
      }

      const badges = this.filterContainer.querySelectorAll('[data-result-count]')
      badges.forEach((badge) => {
        const node = badge.closest('[data-category-uid]')
        if (!node) {
          return
        }

        const uid = node.dataset.categoryUid
        if (!(uid in counts)) {
          return
        }

        const count = counts[uid]
        badge.textContent = count

        // Mirror the count into the adjacent screenreader phrase ("<count> results"), which
        // lives in the .assistive [data-result-count-sr] span right after the decorative badge.
        const srPhrase = badge.nextElementSibling
        if (srPhrase && srPhrase.hasAttribute('data-result-count-sr')) {
          const srValue = srPhrase.querySelector('[data-result-count-value]')
          if (srValue) {
            srValue.textContent = count
          }
        }

        // Only leaf nodes (no children, so they carry a checkbox) get the 0-count treatment.
        if (node.dataset.hasChildren === 'true') {
          return
        }

        this._toggleLeafEmptyState(node, count === 0)
      })
    }

    _toggleLeafEmptyState(node, isEmpty) {
      const emptyModifier = node.classList.contains('pn-category-filter__category-child')
        ? 'pn-category-filter__category-child--empty'
        : 'pn-category-filter__category-item--empty'
      node.classList.toggle(emptyModifier, isEmpty)

      const checkbox = node.querySelector('.pn-category-filter__category-checkbox')
      if (checkbox) {
        // Never disable a checked leaf: disabling it would trap the selection (WCAG 2.1.2) —
        // the user could no longer uncheck it. A checked-but-empty leaf keeps its --empty
        // styling but stays operable so it can be toggled off.
        checkbox.disabled = isEmpty && !checkbox.checked
      }
    }

    attachPaginationListener() {
      document.addEventListener('click', async (event) => {
        const paginationLink = event.target.closest('[data-page-link]')

        if (paginationLink && this.resultsContainer.contains(paginationLink)) {
          event.preventDefault()

          const action = 'categoryFilter'
          if (loadingManager.getLoading(action)) {
            return
          }

          loadingManager.setLoading(true, action)

          try {
            const url = paginationLink.getAttribute('href')

            await ContentLoader.loadContent(url, this.resultsContainer, false, true, 'GET')

            this.resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' })
          } catch (error) {
            console.error('Error loading paginated results:', error)
          } finally {
            loadingManager.setLoading(false, action)
          }

          // Filter, search, sort and reset share this lock, so an interaction during the
          // pagination load only raised the pending flag — drain it here too.
          if (this.pendingReload) {
            await this.queueResultsReload()
          }
        }
      })
    }

    attachMobileToggleListener() {
      const toggleButtons = this.filterContainer.querySelectorAll('.pn-category-filter__toggle')

      if (toggleButtons.length === 0) {
        return
      }

      const targetId = toggleButtons[0].getAttribute('aria-controls')
      if (!targetId) {
        console.warn('Toggle button missing aria-controls attribute')
        return
      }

      const targetElement = document.getElementById(targetId)
      if (!targetElement) {
        console.warn(`Target element with id "${targetId}" not found`)
        return
      }

      toggleButtons.forEach((toggleButton) => {
        toggleButton.addEventListener('click', () => {
          const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true'

          if (isExpanded) {
            this._closeMobileOverlay(toggleButtons, targetElement)
          } else {
            this._openMobileOverlay(toggleButtons, targetElement)
          }
        })
      })

      targetElement.addEventListener('keydown', (e) => this._handleMobileOverlayKeydown(e, toggleButtons[0], targetElement))

      document.addEventListener('click', (e) => {
        const isOverlayOpen = targetElement.classList.contains('is-open')
        const clickedOutside =
          !targetElement.contains(e.target) && !Array.from(toggleButtons).some((btn) => btn.contains(e.target))

        if (isOverlayOpen && clickedOutside) {
          this._closeMobileOverlay(toggleButtons, targetElement)
        }
      })
    }

    _openMobileOverlay(toggleButtons, targetElement) {
      toggleButtons.forEach((btn) => btn.setAttribute('aria-expanded', 'true'))
      targetElement.classList.add('is-open')
      this.filterContainer.classList.add('is-open')
      document.body.classList.add('fixed')

      const focusableElements = this._getFocusableElementsInMenu()
      if (focusableElements.length > 0) {
        focusableElements[0].focus()
      }
    }

    _closeMobileOverlay(toggleButtons, targetElement) {
      toggleButtons.forEach((btn) => btn.setAttribute('aria-expanded', 'false'))
      targetElement.classList.remove('is-open')
      this.filterContainer.classList.remove('is-open')
      document.body.classList.remove('fixed')
      toggleButtons[0].focus()
    }

    _handleMobileOverlayKeydown(event, toggleButton, targetElement) {
      if (event.key === 'Escape') {
        const toggleButtons = this.filterContainer.querySelectorAll('.pn-category-filter__toggle')
        this._closeMobileOverlay(toggleButtons, targetElement)
        return
      }

      if (event.key === 'Tab' && !targetElement.classList.contains('is-open')) {
        return
      }

      if (event.key === 'Tab') {
        this._trapMobileOverlayFocus(event)
      }
    }

    _trapMobileOverlayFocus(event) {
      const focusableElements = this._getFocusableElementsInMenu()

      if (focusableElements.length === 0) {
        event.preventDefault()
        return
      }

      const firstElement = focusableElements[0]
      const lastElement = focusableElements[focusableElements.length - 1]
      const activeElement = document.activeElement

      if (event.shiftKey) {
        if (activeElement === firstElement) {
          event.preventDefault()
          lastElement.focus()
        }
      } else {
        if (activeElement === lastElement) {
          event.preventDefault()
          firstElement.focus()
        }
      }
    }

    _getFocusableElementsInMenu() {
      const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
      return Array.from(this.filterContainer.querySelectorAll(focusableSelector))
    }

    _attachAccordionListener() {
      const details = this.filterContainer.querySelectorAll('details.pn-category-filter__category-item')

      document.addEventListener('click', (e) => {
        details.forEach((detail) => {
          if (detail.open && !detail.contains(e.target)) {
            detail.removeAttribute('open')
          }
        })
      })
    }
  }

  // ---------------------------------------------------------------------------
  // Init
  // ---------------------------------------------------------------------------

  document.addEventListener('DOMContentLoaded', () => {
    const instances = document.querySelectorAll('.pn-category-filter__menu')
    if (!instances.length) return

    instances.forEach((filterMenu) => {
      new CategoryFilterManager(filterMenu)
    })
  })
})()
