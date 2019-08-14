// Types
export const DATA_CHANGED = 'data_changed';
export const DATA_BATCH_CHANGED = 'data_batch_changed';
export const DATA_RESET = 'data_reset';

// Actions
export function setData(key, data) {
  return {
    type: DATA_CHANGED,
    payload: {
      key,
      data,
    },
  };
}

export function setDataBatch(object) {
  return {
    type: DATA_BATCH_CHANGED,
    payload: object,
  };
}

export function resetData() {
  return {
    type: DATA_RESET,
    payload: {},
  };
}
