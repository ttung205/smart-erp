import { apiClient } from './client';

export const identityApi = {
  // Departments (Phòng ban)
  getDepartments() {
    return apiClient('/api/v1/auth/departments');
  },

  createDepartment(data) {
    return apiClient('/api/v1/auth/departments', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateDepartment(id, data) {
    return apiClient(`/api/v1/auth/departments/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  deleteDepartment(id) {
    return apiClient(`/api/v1/auth/departments/${id}`, {
      method: 'DELETE'
    });
  },

  // Users (Nhân viên)
  getUsers() {
    return apiClient('/api/v1/auth/users');
  },

  createUser(data) {
    return apiClient('/api/v1/auth/users', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateUser(id, data) {
    return apiClient(`/api/v1/auth/users/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  // Roles (Vai trò)
  getRoles() {
    return apiClient('/api/v1/auth/roles');
  },

  createRole(data) {
    return apiClient('/api/v1/auth/roles', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateRole(id, data) {
    return apiClient(`/api/v1/auth/roles/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  deleteRole(id) {
    return apiClient(`/api/v1/auth/roles/${id}`, {
      method: 'DELETE'
    });
  },

  updateRolePermissions(roleId, permissionIds) {
    return apiClient(`/api/v1/auth/roles/${roleId}/permissions`, {
      method: 'PUT',
      body: JSON.stringify({ permission_ids: permissionIds })
    });
  },

  // Permissions (Quyền hạn)
  getPermissions() {
    return apiClient('/api/v1/auth/permissions');
  },

  createPermission(data) {
    return apiClient('/api/v1/auth/permissions', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updatePermission(id, data) {
    return apiClient(`/api/v1/auth/permissions/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  deletePermission(id) {
    return apiClient(`/api/v1/auth/permissions/${id}`, {
      method: 'DELETE'
    });
  }
};
