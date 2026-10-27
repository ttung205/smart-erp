import { apiClient } from './client';

const root = '/api/v1/ai/analytics';
const unwrap = (promise) => promise.then((res) => res.data);

export const analyticsApi = {
  getOverview: () => unwrap(apiClient(`${root}/overview`)),
  getForecast: () => unwrap(apiClient(`${root}/forecast`)),
  getRfmClusters: (clusterKey = null) => {
    const url = clusterKey ? `${root}/rfm-clusters?cluster_key=${clusterKey}` : `${root}/rfm-clusters`;
    return unwrap(apiClient(url));
  },
  getCreditRisk: () => unwrap(apiClient(`${root}/credit-risk`)),
  refreshCache: () => unwrap(apiClient(`${root}/refresh`, { method: 'POST' }))
};
