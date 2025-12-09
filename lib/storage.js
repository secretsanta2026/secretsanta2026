// storage.js
// Simple JSON file storage for Secret Santa data
import fs from 'fs';
import path from 'path';

const DATA_FILE = path.join(process.cwd(), 'data.json');

export async function loadData() {
  try {
    if (fs.existsSync(DATA_FILE)) {
      const content = fs.readFileSync(DATA_FILE, 'utf8');
      return JSON.parse(content);
    }
  } catch (error) {
    console.warn('Error loading data.json, using default:', error.message);
  }
  
  // Return default empty structure
  return { participants: {}, assignments: {}, revealed: {} };
}

export async function saveData(data) {
  try {
    // Ensure the directory exists
    const dir = path.dirname(DATA_FILE);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    
    // Write the JSON file
    fs.writeFileSync(DATA_FILE, JSON.stringify(data, null, 2), 'utf8');
    return true;
  } catch (error) {
    console.error('Error saving data.json:', error.message);
    throw new Error(`Failed to save data: ${error.message}`);
  }
}

export default { loadData, saveData };
