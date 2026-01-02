# UI Design for Workspace Page

## Overview

The UI/UX design depends on the view mode. The following view modes are supported:

- **List View Mode**
- **Kanban View Mode**
- **Weekly Timegrid View Mode**

---

## List View Mode

### CREATE NEW ITEM

There are two ways to create or show the create form:

- **Add Button**: When clicked, it shows a modal
- **CTA Card**: Inside the card container, there's an empty create card that only contains a "+" icon

**UI Design Requirements for Create Item:**

- Easy to use and doesn't overwhelm the user
- Only one input field for the title
- Other properties can be configured using a popover or dropdown menu (an icon button that, upon hovering, shows the options to choose from)

### VIEW ITEM

- **Card Interaction**: Each item card is fully clickable and opens a slide-out drawer on the right side of the screen to display the item's details

**View Item Details:**

- Users can view all the details of the item
- Users can also edit or delete items
- Include a focus mode button somewhere (do not implement functionality yet - will be implemented later)

### PROPERTIES OF THE ITEM

Each item card displays clearly visible labels:

- **Item Type**: The type of item (Task, Event, Project)
- **Status Indicators**: Status, complexity, and priority (should be easy to see, so utilize colors)
- **Additional Information**: Other standard information about the item, such as:
  - Title
  - Dates
  - Tags
  - etc.

### QUICK UPDATE STATUS

Each item has 2 buttons for quick status updates:

- **Icon Button**: Change status to "doing" or "processing"
- **Icon Button**: Change status to "done" or "completed"
