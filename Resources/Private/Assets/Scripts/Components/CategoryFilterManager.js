/**
 * CategoryFilterManager
 *
 * Manages category filtering with AJAX updates and URL state management
 *
 * @module CategoryFilterManager
 * @author ProudNerds
 * @version 1.0.0
 */

import ContentLoader from '../Utils/ContentLoader.js'
import LoadingManager from '../Utils/LoadingManager.js'
import SortingManager from './SortingManager.js'
import SearchManager from './SearchManager.js'

/**
 * Manages category filtering with AJAX
 *
 * @class
 */
class CategoryFilterManager {
  /**
   * Creates an instance of CategoryFilterManager
   *
   * @constructor
   * @param {HTMLElement} filterContainer - The filter container element
   */
  constructor(filterContainer) {
    this.filterContainer = filterContainer
    this.resultsContainer = document.querySelector('.pn-category-filter__results')
    this.checkboxes = filterContainer.querySelectorAll('.pn-category-filter__category-checkbox')
    this.isResetting = false
    this.pendingReload = false

    // Look up the root container to find elements placed outside the menu aside (vertical mode)
    this.filterRoot = filterContainer.closest('.pn-category-filter') ?? filterContainer.parentElement
    const filterRoot = this.filterRoot

    // Initialize SortingManager if sort container exists
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

  /**
   * Initializes the manager
   *
   * @returns {void}
   */
  init() {
    this.attachEventListeners()
    this.applyFiltersFromUrl()
    this.attachContentUpdateListener()
    this.attachResetButtonListener()
    this.attachPaginationListener()
    this.attachMobileToggleListener()
    if (this.filterRoot.classList.contains('pn-category-filter--horizontal')) {
      this.#attachAccordionListener()
    }
  }

  /**
   * Attaches event listeners to checkboxes
   *
   * @returns {void}
   */
  attachEventListeners() {
    this.checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', () => this.handleFilterChange())
    })
  }

  /**
   * Attaches event listener to reset button using event delegation
   *
   * @returns {void}
   */
  attachResetButtonListener() {
    // Use event delegation on the filter root to catch reset buttons in vertical mode (outside the menu aside)
    this.filterRoot.addEventListener('click', (e) => {
      const resetButton = e.target.closest('[data-action="reset-filters"]')
      if (resetButton) {
        e.preventDefault()
        this.handleReset()
      }
    })
  }

  /**
   * Handles reset button click - clears all filters and reloads results
   *
   * @returns {Promise<void>}
   */
  async handleReset() {
    // Uncheck all category checkboxes
    this.checkboxes.forEach((checkbox) => {
      checkbox.checked = false
    })

    // Reset sorting back to defaults (no sorting)
    if (this.sortingManager) {
      this.sortingManager.setSorting('', 'none')
    }

    // Clear search input
    if (this.searchManager) {
      this.searchManager.setSearchTerm('')
    }

    // Always clean the URL when resetting (remove query parameters)
    const cleanUrl = window.location.pathname
    window.history.pushState({}, '', cleanUrl)

    // Set flag to prevent ContentLoaderUpdated from overwriting the clean URL
    this.isResetting = true

    await this.queueResultsReload()
  }

  /**
   * Attaches listener for content updates to handle pushState
   *
   * @returns {void}
   */
  attachContentUpdateListener() {
    document.addEventListener('ContentLoaderUpdated', (event) => {
      // Check if this is our results container
      if (event.detail.targetElement === this.resultsContainer) {
        // Skip pushState update if we're resetting (we already set clean URL),
        // but always re-attach SearchManager and update badges.
        if (!this.isResetting) {
          // Look for pushstate URL in the updated content
          const pushStateElement = this.resultsContainer.querySelector('[data-pushstate-url]')
          if (pushStateElement && pushStateElement.dataset.pushstateUrl) {
            // Decode the URL to make it readable in the browser address bar
            const decodedUrl = decodeURIComponent(pushStateElement.dataset.pushstateUrl)
            window.history.pushState({}, '', decodedUrl)
          }
        }

        // Re-attach SearchManager to the new form element if the form is inside the
        // results container (it gets replaced on every AJAX update).
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

        // Re-attach SortingManager when sort UI is inside the replaced results container
        // (vertical displayMode renders FilterMenu/Actions in Results.html).
        const newSortContainer = this.resultsContainer.querySelector('.pn-category-filter__sort')
        if (newSortContainer) {
          this.sortingManager = new SortingManager(newSortContainer, (sortData) => {
            this.handleSortChange(sortData)
          })
        }

        // Update category amount badges after content update
        this.updateCategoryAmounts()

        // Patch the result-count badges (and 0-count leaf disabled state) from the
        // server counts map that ships inside the swapped results container.
        this.applyResultCounts()
      }
    })
  }

  /**
   * Handles filter change event
   *
   * @returns {Promise<void>}
   */
  async handleFilterChange() {
    await this.queueResultsReload()
  }

  /**
   * Handles sort change event from SortingManager
   *
   * @param {Object} _sortData - Sort data (unused, kept for interface consistency)
   * @returns {Promise<void>}
   */
  async handleSortChange(_sortData) {
    await this.queueResultsReload()
  }

  /**
   * Handles search change event from SearchManager
   *
   * @param {string} _searchTerm - Search term (unused, kept for interface consistency)
   * @returns {Promise<void>}
   */
  async handleSearchChange(_searchTerm) {
    await this.queueResultsReload()
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
    const action = 'categoryFilter'

    if (LoadingManager.getLoading(action)) {
      this.pendingReload = true
      return
    }

    LoadingManager.setLoading(true, action)

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
      // isResetting is scoped to this run: every pass of the loop must skip the pushState,
      // so the clean URL set by handleReset() survives a reset that queued behind a load.
      this.isResetting = false
      LoadingManager.setLoading(false, action)
    }
  }

  /**
   * Loads filtered results via AJAX
   *
   * @returns {Promise<void>}
   */
  async loadResults() {
    const firstCheckbox = this.checkboxes[0]

    if (!firstCheckbox || !firstCheckbox.dataset.ajaxUrl) {
      console.error('No checkbox or AJAX URL found')
      return
    }

    // Get base URL for POST request
    const baseUrl = firstCheckbox.dataset.ajaxUrl
    const checkedBoxes = Array.from(this.checkboxes).filter((cb) => cb.checked)

    // Build form data for POST request (avoids cHash issues)
    const formData = {}

    // Add base parameters from URL, but exclude any existing selectedCategories
    const urlObj = new URL(baseUrl, window.location.origin)
    urlObj.searchParams.forEach((value, key) => {
      // Skip old selectedCategories parameters - we'll add fresh ones below
      if (!key.includes('[selectedCategories]')) {
        formData[key] = value
      }
    })

    // Add selected categories (sorted by ID to ensure consistent URL parameter order)
    const selectedCategories = checkedBoxes.map((cb) => cb.value).sort((a, b) => parseInt(a, 10) - parseInt(b, 10))

    if (selectedCategories.length > 0) {
      // Add each selected category
      selectedCategories.forEach((categoryId, index) => {
        formData[`tx_pncategoryfilter_categoryfilterlist[selectedCategories][${index}]`] = categoryId
      })
    }
    // When no categories selected (reset), don't add selectedCategories key at all
    // This tells the server to use default behavior (show all records from FlexForm categories)

    // Add sorting parameters if SortingManager is available
    if (this.sortingManager) {
      const { sortField, sortOrder } = this.sortingManager.getCurrentSorting()
      if (sortField && sortOrder !== 'none') {
        formData['tx_pncategoryfilter_categoryfilterlist[sorting]'] = sortOrder
        formData['tx_pncategoryfilter_categoryfilterlist[sortField]'] = sortField
      }
    }

    // Add search parameters if SearchManager is available
    if (this.searchManager) {
      const searchTerm = this.searchManager.getSearchTerm()
      if (searchTerm) {
        formData['tx_pncategoryfilter_categoryfilterlist[search]'] = searchTerm
      }
    }

    // Use POST to avoid cHash validation
    await ContentLoader.loadContent(
      urlObj.pathname + urlObj.search,
      this.resultsContainer,
      false, // append
      true, // showSpinner
      'POST',
      formData
    )
  }

  /**
   * Gets selected category UIDs (sorted numerically)
   *
   * @returns {number[]}
   */
  getSelectedCategories() {
    const selected = []
    this.checkboxes.forEach((checkbox) => {
      if (checkbox.checked) {
        selected.push(parseInt(checkbox.value, 10))
      }
    })
    return selected.sort((a, b) => a - b)
  }

  /**
   * Applies filters from URL parameters on page load
   *
   * @returns {void}
   */
  applyFiltersFromUrl() {
    const urlParams = new URLSearchParams(window.location.search)
    const selectedCategories = []

    // Parse selected categories from URL - URLSearchParams handles the [] in parameter names
    urlParams.forEach((value, key) => {
      // Match both formats: [selectedCategories][] and [selectedCategories][0], [selectedCategories][1], etc.
      if (key.includes('tx_pncategoryfilter_categoryfilterlist[selectedCategories]')) {
        selectedCategories.push(parseInt(value, 10))
      }
    })

    // Update checkboxes to match URL state
    if (selectedCategories.length > 0) {
      this.checkboxes.forEach((checkbox) => {
        const categoryId = parseInt(checkbox.value, 10)
        checkbox.checked = selectedCategories.includes(categoryId)
      })
    }

    // Update category amount badges
    this.updateCategoryAmounts()
  }

  /**
   * Updates the amount badges for parent categories
   * Shows the badge only when one or more child filters are active
   * Displays the count of active child filters
   *
   * @returns {void}
   */
  updateCategoryAmounts() {
    const parentCategories = this.filterContainer.querySelectorAll('[data-has-children="true"]')

    parentCategories.forEach((parentItem) => {
      const amountBadge = parentItem.querySelector('.pn-category-filter__amount')

      if (!amountBadge) {
        return
      }

      const childCheckboxes = parentItem.querySelectorAll('.pn-category-filter__category-checkbox')

      // Count how many children are checked
      const activeChildCount = Array.from(childCheckboxes).filter((cb) => cb.checked).length

      if (activeChildCount > 0) {
        amountBadge.textContent = activeChildCount
        amountBadge.classList.remove('hidden')
      } else {
        amountBadge.classList.add('hidden')
      }
    })
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

      this.#toggleLeafEmptyState(node, count === 0)
    })
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

  /**
   * Attaches event listener for pagination links
   * Intercepts clicks on pagination links with data-page-link attribute to load via AJAX
   *
   * @returns {void}
   */
  attachPaginationListener() {
    // Use event delegation on the results container
    document.addEventListener('click', async (event) => {
      const paginationLink = event.target.closest('[data-page-link]')

      if (paginationLink && this.resultsContainer.contains(paginationLink)) {
        event.preventDefault()

        const action = 'categoryFilter'
        if (LoadingManager.getLoading(action)) {
          return
        }

        LoadingManager.setLoading(true, action)

        try {
          const url = paginationLink.getAttribute('href')

          // Load paginated results via AJAX
          await ContentLoader.loadContent(
            url,
            this.resultsContainer,
            false, // append
            true, // showSpinner
            'GET'
          )

          // Scroll to results container after pagination
          this.resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' })
        } catch (error) {
          console.error('Error loading paginated results:', error)
        } finally {
          LoadingManager.setLoading(false, action)
        }

        // Filter, search, sort and reset share this lock, so an interaction during the
        // pagination load only raised the pending flag — drain it here too.
        if (this.pendingReload) {
          await this.queueResultsReload()
        }
      }
    })
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
          this.#closeMobileOverlay(toggleButton, targetElement)
        } else {
          this.#openMobileOverlay(toggleButton, targetElement)
        }
      })
    })

    targetElement.addEventListener('keydown', (e) =>
      this.#handleMobileOverlayKeydown(e, toggleButtons[0], targetElement)
    )

    document.addEventListener('click', (e) => {
      const isOverlayOpen = targetElement.classList.contains('is-open')
      const clickedOutside =
        !targetElement.contains(e.target) && !Array.from(toggleButtons).some((btn) => btn.contains(e.target))

      if (isOverlayOpen && clickedOutside) {
        this.#closeMobileOverlay(toggleButtons[0], targetElement)
      }
    })
  }

  /**
   * Opens the mobile filter overlay with focus management and scroll prevention
   * Updates all toggle buttons to reflect expanded state
   *
   * @private
   */
  #openMobileOverlay(toggleButton, targetElement) {
    const toggleButtons = this.filterContainer.querySelectorAll('.pn-category-filter__toggle')
    toggleButtons.forEach((btn) => btn.setAttribute('aria-expanded', 'true'))
    targetElement.classList.add('is-open')
    this.filterContainer.classList.add('is-open')
    document.body.classList.add('fixed')

    // Focus first focusable element in the filter panel
    const focusableElements = this.#getFocusableElementsInMenu()
    if (focusableElements.length > 0) {
      focusableElements[0].focus()
    }
  }

  /**
   * Closes the mobile filter overlay and restores scroll
   * Updates all toggle buttons to reflect collapsed state
   *
   * @private
   */
  #closeMobileOverlay(toggleButton, targetElement) {
    const toggleButtons = this.filterContainer.querySelectorAll('.pn-category-filter__toggle')
    toggleButtons.forEach((btn) => btn.setAttribute('aria-expanded', 'false'))
    targetElement.classList.remove('is-open')
    this.filterContainer.classList.remove('is-open')
    document.body.classList.remove('fixed')
    toggleButton.focus()
  }

  /**
   * Handles keyboard events in the mobile overlay
   * Closes on Escape key, traps Tab focus within the menu
   *
   * @private
   */
  #handleMobileOverlayKeydown(event, toggleButton, targetElement) {
    if (event.key === 'Escape') {
      this.#closeMobileOverlay(toggleButton, targetElement)
      return
    }

    if (event.key === 'Tab' && !targetElement.classList.contains('is-open')) {
      return
    }

    if (event.key === 'Tab') {
      this.#trapMobileOverlayFocus(event)
    }
  }

  /**
   * Traps focus within the filter menu for WCAG compliance
   *
   * @private
   */
  #trapMobileOverlayFocus(event) {
    const focusableElements = this.#getFocusableElementsInMenu()

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

  /**
   * Gets all focusable elements within the filter menu
   *
   * @returns {HTMLElement[]}
   * @private
   */
  #getFocusableElementsInMenu() {
    const focusableSelector = `
      button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])
    `
    return Array.from(this.filterContainer.querySelectorAll(focusableSelector))
  }

  /**
   * Closes all other <details> category items when one is opened (accordion behaviour)
   *
   * @private
   */
  #attachAccordionListener() {
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

export default CategoryFilterManager
