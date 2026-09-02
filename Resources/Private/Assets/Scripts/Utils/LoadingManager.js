/**
 * @classDesc Helper class for managing loading state, to prevent multiple load requests.
 *
 * ## Usage:
 *
 * // Import the singleton instance
 * import LoadingManager from './LoadingManager'
 *
 * // Global loading state (default, backward compatible)
 * if (!LoadingManager.getLoading()) {
 *   LoadingManager.setLoading(true)
 *   // ...do work...
 *   LoadingManager.setLoading(false)
 * }
 *
 * // Per-action loading state (prevents concurrent loads for the same action)
 * const action = 'loadMoreThingies'
 * if (!LoadingManager.getLoading(action)) {
 *   LoadingManager.setLoading(true, action)
 *   // ...do work for this action...
 *   LoadingManager.setLoading(false, action)
 * }
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

const instance = new LoadingManager()
export default instance
