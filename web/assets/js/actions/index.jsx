// Types
export const DATA_CHANGED = 'data_changed';
export const DATA_RESET = 'data_reset';

// Actions
export function setData(data, type) {
  return {
    type: DATA_CHANGED,
    payload: {
      type,
      data,
    },
  };
}

export function resetData() {
  return {
    type: DATA_RESET,
    payload: {},
  };
}
