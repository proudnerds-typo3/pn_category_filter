/**
 * PnCategoryFilter
 *
 * Main initialization for Category Filter functionality
 *
 * @module PnCategoryFilter
 */

import CategoryFilterManager from './Components/CategoryFilterManager.js'

document.addEventListener('DOMContentLoaded', () => {
  const instances = document.querySelectorAll('.pn-category-filter__menu')
  if (!instances.length) return

  instances.forEach((filterMenu) => {
    new CategoryFilterManager(filterMenu)
  })
})
