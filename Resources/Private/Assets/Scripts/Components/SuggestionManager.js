/**
 * SuggestionManager
 *
 * Turns the search input into an ARIA 1.2 combobox with title and category suggestions.
 * The suggestions arrive with every results update, so there is no request of its own.
 *
 * @module SuggestionManager
 */

class SuggestionManager {
  #input
  #listbox
  #status
  #options = []
  #activeIndex = -1
  // After a choice the list stays closed until the visitor types again
  #waitForInput = false
  #minChars
  #onSelectTitle
  #onSelectCategory

  /**
   * @param {HTMLFormElement} searchForm - The search form containing the listbox
   * @param {{onSelectTitle: Function, onSelectCategory: Function}} callbacks
   */
  constructor(searchForm, { onSelectTitle, onSelectCategory }) {
    this.#input = searchForm.querySelector('.pn-category-filter__search-input')
    this.#listbox = searchForm.querySelector('[data-search-suggestions]')
    this.#status = searchForm.querySelector('[data-search-suggestions-status]')
    this.#onSelectTitle = onSelectTitle
    this.#onSelectCategory = onSelectCategory
    this.#minChars = parseInt(searchForm.dataset.searchMinChars || '3', 10)

    if (this.#input && this.#listbox) {
      this.#init(searchForm)
    }
  }

  /**
   * @param {HTMLFormElement} searchForm
   * @returns {boolean}
   */
  static isEnabled(searchForm) {
    return Boolean(searchForm?.querySelector('[data-search-suggestions]'))
  }

  #init(searchForm) {
    this.#input.setAttribute('role', 'combobox')
    this.#input.setAttribute('aria-autocomplete', 'list')
    this.#input.setAttribute('aria-expanded', 'false')
    this.#input.setAttribute('aria-controls', this.#listbox.id)

    // Capture on the form so these keys are handled before the search input's own Enter/Escape handling
    searchForm.addEventListener('keydown', (event) => this.#handleKeydown(event), true)
    this.#input.addEventListener('blur', () => this.close())
    this.#input.addEventListener('input', () => {
      this.#waitForInput = false
      // Below the minimum no new suggestions arrive, so the old ones must not stay choosable
      if (this.#input.value.trim().length < this.#minChars) {
        this.update(null)
      }
    })

    // Keep focus in the input while choosing with the mouse
    this.#listbox.addEventListener('mousedown', (event) => event.preventDefault())
    this.#listbox.addEventListener('click', (event) => {
      const option = event.target.closest('[role="option"]')
      if (option) {
        this.#select(this.#options.indexOf(option))
      }
    })
  }

  /**
   * Shows the suggestions of the latest results update; opens only while the visitor is typing.
   *
   * @param {{titles: string[], categories: {uid: number, title: string, count: number}[]}|null} data
   * @returns {void}
   */
  update(data) {
    if (!this.#listbox) return

    const titles = data?.titles ?? []
    const categories = data?.categories ?? []
    this.#listbox.replaceChildren()
    this.#options = []
    this.#activeIndex = -1

    const categoryLabel = this.#listbox.dataset.categoryLabel || 'Category'
    categories.forEach((category) => {
      this.#addOption('category', String(category.uid), `${categoryLabel}: ${category.title} (${category.count})`)
    })
    titles.forEach((title) => this.#addOption('title', title, title))

    if (this.#options.length === 0 || this.#waitForInput || document.activeElement !== this.#input) {
      this.close()
      if (this.#status && this.#options.length === 0) this.#status.textContent = ''
      return
    }

    this.#open()
  }

  #open() {
    this.#listbox.hidden = false
    this.#input.setAttribute('aria-expanded', 'true')
    this.#input.removeAttribute('aria-activedescendant')
    if (this.#status) {
      this.#status.textContent = (this.#listbox.dataset.countLabel || '%s').replace('%s', String(this.#options.length))
    }
  }

  /**
   * @returns {void}
   */
  close() {
    if (!this.#listbox) return

    this.#listbox.hidden = true
    this.#input.setAttribute('aria-expanded', 'false')
    this.#input.removeAttribute('aria-activedescendant')
    this.#setActive(-1)
  }

  #addOption(type, value, label) {
    const option = document.createElement('li')
    option.id = `${this.#listbox.id}-option-${this.#options.length}`
    option.className = `pn-category-filter__suggestion pn-category-filter__suggestion--${type}`
    option.setAttribute('role', 'option')
    option.setAttribute('aria-selected', 'false')
    option.dataset.suggestionType = type
    option.dataset.suggestionValue = value
    option.textContent = label
    this.#listbox.appendChild(option)
    this.#options.push(option)
  }

  #isOpen() {
    return !this.#listbox.hidden && this.#options.length > 0
  }

  #handleKeydown(event) {
    if (event.target !== this.#input) return

    // A closed list with suggestions reopens on ArrowDown, as a combobox should
    if (!this.#isOpen()) {
      if (event.key === 'ArrowDown' && this.#options.length > 0) {
        event.preventDefault()
        this.#open()
        this.#setActive(0)
      }
      return
    }

    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        this.#setActive((this.#activeIndex + 1) % this.#options.length)
        break
      case 'ArrowUp':
        event.preventDefault()
        this.#setActive(this.#activeIndex <= 0 ? this.#options.length - 1 : this.#activeIndex - 1)
        break
      case 'Enter':
        if (this.#activeIndex >= 0) {
          event.preventDefault()
          event.stopPropagation()
          this.#select(this.#activeIndex)
        } else {
          this.close()
        }
        break
      case 'Escape':
        // Only close; a second Escape clears the field as usual
        event.preventDefault()
        event.stopPropagation()
        this.close()
        break
      case 'Tab':
        this.close()
        break
    }
  }

  #setActive(index) {
    this.#options.forEach((option, i) => option.setAttribute('aria-selected', String(i === index)))
    this.#activeIndex = index
    if (index >= 0) {
      this.#input.setAttribute('aria-activedescendant', this.#options[index].id)
      this.#options[index].scrollIntoView({ block: 'nearest' })
    } else {
      this.#input.removeAttribute('aria-activedescendant')
    }
  }

  #select(index) {
    const option = this.#options[index]
    if (!option) return

    const { suggestionType, suggestionValue } = option.dataset
    this.close()
    this.#waitForInput = true
    if (suggestionType === 'category') {
      this.#onSelectCategory(suggestionValue)
    } else {
      this.#input.value = suggestionValue
      this.#onSelectTitle(suggestionValue)
    }
  }
}

export default SuggestionManager
