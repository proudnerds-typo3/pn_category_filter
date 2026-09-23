/**
 * PnCategoryFilter
 *
 * Main initialization for Category Filter functionality
 *
 * @module PnCategoryFilter
 */

import CategoryFilterManager from './Components/CategoryFilterManager.js'

const init = () => {
  const instances = document.querySelectorAll('.pn-category-filter__menu')
  if (!instances.length) return

  instances.forEach((filterMenu) => {
    new CategoryFilterManager(filterMenu)
  })
}

// A dynamic import() can resolve after DOMContentLoaded has already fired.
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init)
} else {
  init()
}
