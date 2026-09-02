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

  /**
   * Fade out the content of the target element
   * @param {HTMLElement} targetElement - The element to fade out
   * @returns {Promise<void>}
   */
  static async fadeOutContent(targetElement) {
    targetElement.classList.add('fade-out')
    const originalHeight = targetElement.offsetHeight
    targetElement.style.height = `${originalHeight}px`
    await new Promise((resolve) => setTimeout(resolve, 150))
  }

  /**
   * Clear the content of the target element and show the spinner
   * @param {HTMLElement} targetElement - The element to clear
   * @param {HTMLElement} spinner - The spinner element to show
   */
  static clearContent(targetElement, spinner) {
    targetElement.innerHTML = ''
    if (spinner) {
      targetElement.appendChild(spinner)
    }
    targetElement.classList.remove('fade-out')
  }

  /**
   * Fetch content from the given URL using the specified method
   * @param {string} url - The URL to fetch content from
   * @param {string} method - The HTTP method to use (GET or POST)
   * @param {Object} [data] - The data to send with the request (for POST method)
   * @returns {Promise<string>} - The fetched content as a string
   */
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

  /**
   * Replace the content of the target element and fade in the new content
   * @param {HTMLElement} targetElement - The element to replace content in
   * @param {string} content - The new content to insert
   * @returns {Promise<void>}
   */
  static async replaceContent(targetElement, content) {
    targetElement.innerHTML = content
    targetElement.classList.add('fade-in')
    await new Promise((resolve) => setTimeout(resolve, 150))
    targetElement.classList.remove('fade-in')
    targetElement.style.height = ''
  }

  /**
   * Remove the spinner from the target element
   * @param {HTMLElement} targetElement - The element to remove the spinner from
   * @param {HTMLElement} spinner - The spinner element to remove
   */
  static removeSpinner(targetElement, spinner) {
    if (spinner.parentNode === targetElement) {
      targetElement.removeChild(spinner)
    }
  }

  /**
   * Show an error message in the target element
   * @param {HTMLElement} targetElement - The element to show the error message in
   */
  static showError(targetElement) {
    const errorMessage = `<p class="mt-4 p-2 pn-social-ads-loading__error">Fout bij het laden van de inhoud. Als deze fout blijft optreden, neem dan svp contact op.</p>`

    targetElement.innerHTML = errorMessage
  }
}

export default ContentLoader
