// storage.js
// Smart storage that works on both local and serverless environments
import fs from 'fs';
import path from 'path';

const DATA_FILE = path.join(process.cwd(), 'data.json');

// In-memory storage for serverless environments (Vercel)
let memoryData = null;

function isServerless() {
  // Detect if we're running in a serverless environment
  return process.env.VERCEL || process.env.AWS_LAMBDA_FUNCTION_NAME || process.env.FUNCTION_NAME;
}

export async function loadData() {
  // If serverless, use memory storage
  if (isServerless()) {
    if (memoryData) {
      console.log('Loading data from memory (serverless mode)');
      return memoryData;
    }
    console.log('No data in memory, returning default (serverless mode)');
    return { participants: {}, assignments: {}, revealed: {} };
  }

  // Local development: use file storage
  try {
    if (fs.existsSync(DATA_FILE)) {
      const content = fs.readFileSync(DATA_FILE, 'utf8');
      console.log('Loading data from file (local mode)');
      return JSON.parse(content);
    }
  } catch (error) {
    console.warn('Error loading data.json, using default:', error.message);
  }
  
  console.log('No file found, returning default (local mode)');
  return { participants: {}, assignments: {}, revealed: {} };
}

export async function saveData(data) {
  // If serverless, use memory storage
  if (isServerless()) {
    memoryData = { ...data }; // Store a copy in memory
    console.log('Data saved to memory (serverless mode)');
    return true;
  }

  // Local development: use file storage
  try {
    const dir = path.dirname(DATA_FILE);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    
    fs.writeFileSync(DATA_FILE, JSON.stringify(data, null, 2), 'utf8');
    console.log('Data saved to file (local mode)');
    return true;
  } catch (error) {
    console.error('Error saving data.json:', error.message);
    throw new Error(`Failed to save data: ${error.message}`);
  }
}

export default { loadData, saveData };
