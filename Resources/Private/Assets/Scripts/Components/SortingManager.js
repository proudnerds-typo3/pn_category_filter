/**
 * SortingManager
 *
 * Manages sorting options for filtered results with AJAX updates
 *
 * @module SortingManager
 * @author ProudNerds
 * @version 1.0.0
 */

/**
 * Manages sorting functionality
 *
 * @class
 */
class SortingManager {
  /**
   * Creates an instance of SortingManager
   *
   * @constructor
   * @param {HTMLElement} sortContainer - The sort container element
   * @param {Function} onSortChange - Callback when sort changes
   */
  constructor(sortContainer, onSortChange) {
    this.sortContainer = sortContainer
    this.onSortChange = onSortChange
    this.checkboxes = sortContainer.querySelectorAll('.pn-category-filter__sort-checkbox')
    this.selects = sortContainer.querySelectorAll('.pn-category-filter__sort-select')

    this.init()
  }

  /**
   * Initializes the manager
   *
   * @returns {void}
   */
  init() {
    this.attachCheckboxListeners()
    this.attachSelectListeners()
    this.initializeVisibility()
  }

  /**
   * Initializes select visibility based on checkbox state
   *
   * @returns {void}
   */
  initializeVisibility() {
    this.checkboxes.forEach((checkbox) => {
      const sortField = checkbox.dataset.sortField
      if (!checkbox.checked) {
        this.hideSelectForField(sortField)
      }
    })
  }

  /**
   * Attaches event listeners to checkboxes
   *
   * @returns {void}
   */
  attachCheckboxListeners() {
    this.checkboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', (e) => {
        this.handleCheckboxChange(e)
      })
    })
  }

  /**
   * Attaches event listeners to select dropdowns
   *
   * @returns {void}
   */
  attachSelectListeners() {
    this.selects.forEach((select) => {
      select.addEventListener('change', (e) => {
        this.handleSelectChange(e)
      })
    })
  }

  /**
   * Handles checkbox change - only one checkbox can be selected at a time
   *
   * @param {Event} event - The change event
   * @returns {void}
   */
  handleCheckboxChange(event) {
    const checkbox = event.target
    const sortField = checkbox.dataset.sortField
    const isChecked = checkbox.checked

    if (isChecked) {
      // Uncheck all other checkboxes (only one sort field at a time)
      this.checkboxes.forEach((cb) => {
        if (cb !== checkbox) {
          cb.checked = false
          this.hideSelectForField(cb.dataset.sortField)
        }
      })

      // Show select for this field
      this.showSelectForField(sortField)

      // Get current sorting direction
      const select = this.getSelectForField(sortField)
      const sortOrder = select ? select.value : 'desc'

      // Trigger sort change
      this.triggerSortChange(sortField, sortOrder)
    } else {
      // Hide select for this field
      this.hideSelectForField(sortField)

      // Trigger sort change with no sorting
      this.triggerSortChange('', 'none')
    }
  }

  /**
   * Handles select dropdown change
   *
   * @param {Event} event - The change event
   * @returns {void}
   */
  handleSelectChange(event) {
    const select = event.target
    const sortField = select.dataset.sortField
    const sortOrder = select.value

    // Ensure the checkbox for this field is checked
    const checkbox = this.getCheckboxForField(sortField)
    if (checkbox && !checkbox.checked) {
      checkbox.checked = true
    }

    // Trigger sort change
    this.triggerSortChange(sortField, sortOrder)
  }

  /**
   * Shows select dropdown for a field
   *
   * @param {string} sortField - The field name
   * @returns {void}
   */
  showSelectForField(sortField) {
    const select = this.getSelectForField(sortField)
    if (select) {
      select.style.display = ''
      select.removeAttribute('style')
    }
  }

  /**
   * Hides select dropdown for a field
   *
   * @param {string} sortField - The field name
   * @returns {void}
   */
  hideSelectForField(sortField) {
    const select = this.getSelectForField(sortField)
    if (select) {
      select.style.display = 'none'
    }
  }

  /**
   * Gets select element for a field
   *
   * @param {string} sortField - The field name
   * @returns {HTMLSelectElement|null}
   */
  getSelectForField(sortField) {
    return this.sortContainer.querySelector(`.pn-category-filter__sort-select[data-sort-field="${sortField}"]`)
  }

  /**
   * Gets checkbox element for a field
   *
   * @param {string} sortField - The field name
   * @returns {HTMLInputElement|null}
   */
  getCheckboxForField(sortField) {
    return this.sortContainer.querySelector(`.pn-category-filter__sort-checkbox[data-sort-field="${sortField}"]`)
  }

  /**
   * Triggers sort change callback
   *
   * @param {string} sortField - The field to sort on
   * @param {string} sortOrder - The sort order (asc/desc/none)
   * @returns {void}
   */
  triggerSortChange(sortField, sortOrder) {
    if (typeof this.onSortChange === 'function') {
      this.onSortChange({ sortField, sortOrder })
    }
  }

  /**
   * Gets current sorting state
   *
   * @returns {{sortField: string, sortOrder: string}}
   */
  getCurrentSorting() {
    // Find checked checkbox
    const checkedCheckbox = Array.from(this.checkboxes).find((cb) => cb.checked)

    if (!checkedCheckbox) {
      return { sortField: '', sortOrder: 'none' }
    }

    const sortField = checkedCheckbox.dataset.sortField
    const select = this.getSelectForField(sortField)
    const sortOrder = select ? select.value : 'desc'

    return { sortField, sortOrder }
  }

  /**
   * Sets sorting state (e.g., after AJAX update)
   *
   * @param {string} sortField - The field to sort on
   * @param {string} sortOrder - The sort order (asc/desc/none)
   * @returns {void}
   */
  setSorting(sortField, sortOrder) {
    // Uncheck all checkboxes first
    this.checkboxes.forEach((cb) => {
      cb.checked = false
      this.hideSelectForField(cb.dataset.sortField)
    })

    // If no sorting, we're done
    if (!sortField || sortOrder === 'none') {
      return
    }

    // Check the appropriate checkbox
    const checkbox = this.getCheckboxForField(sortField)
    if (checkbox) {
      checkbox.checked = true
      this.showSelectForField(sortField)
    }

    // Set select value
    const select = this.getSelectForField(sortField)
    if (select) {
      select.value = sortOrder
    }
  }
}

export default SortingManager
