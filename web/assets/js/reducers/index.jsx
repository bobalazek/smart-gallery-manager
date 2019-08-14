import {
  DATA_CHANGED,
  DATA_RESET,
} from '../actions/index';

const initialState = {
  rows: [],
  rowsIndexes: [],
  files: [],
  filesMap: [],
  filesSummary: {},
};

const rootReducer = (state = JSON.parse(JSON.stringify(initialState)), action) => {
  if (action.type === DATA_CHANGED) {
    state[action.payload.type] = action.payload.data;
  } else if (action.type === DATA_RESET) {
    state = JSON.parse(JSON.stringify(initialState));
  }

  return state;
};

export default rootReducer;
