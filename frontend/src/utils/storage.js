const TOKEN_KEY = 'smart_erp_token';

export const storage = {
  getToken() {
    return localStorage.getItem(TOKEN_KEY) || '';
  },
  setToken(token) {
    if (token) {
      localStorage.setItem(TOKEN_KEY, token);
    } else {
      localStorage.removeItem(TOKEN_KEY);
    }
  },
  removeToken() {
    localStorage.removeItem(TOKEN_KEY);
  }
};
