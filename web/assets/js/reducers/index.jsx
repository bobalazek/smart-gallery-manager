import {
  DATA_CHANGED,
  DATA_BATCH_CHANGED,
  DATA_RESET,
} from '../actions/index';

const initialState = {
  isLoading: false,
  isLoaded: false,
  rows: [],
  rowsIndexes: [],
  files: [],
  filesMap: [],
  filesSummary: {},
  orderBy: 'taken_at',
};

const rootReducer = (state = JSON.parse(JSON.stringify(initialState)), action) => {
  if (action.type === DATA_CHANGED) {
    state = Object.assign(
      {},
      state,
      {
        [action.payload.key]: action.payload.data,
      }
    );
  } else if (action.type === DATA_BATCH_CHANGED) {
    state = Object.assign(
      {},
      state,
      action.payload
    );
  } else if (action.type === DATA_RESET) {
    state = JSON.parse(JSON.stringify(initialState));
  }

  return state;
};

export default rootReducer;
