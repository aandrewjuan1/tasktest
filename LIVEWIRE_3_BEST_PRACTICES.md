# Livewire 3 Best Practices Guide

## Overview

This document outlines best practices for developing with Livewire 3, based on modern Laravel and Livewire conventions. These guidelines help ensure maintainable, accessible, and performant applications.

## What Was Implemented

The following enhancements were added to the workspace components:

- **Toast Notification System**: Reusable component for user feedback
- **Loading States**: Visual indicators during async operations
- **Error Handling**: Try-catch blocks with user-friendly notifications
- **Accessibility Improvements**: ARIA labels, keyboard navigation, semantic HTML
- **Smooth Transitions**: Fade effects for view switching
- **Optimistic Updates**: Immediate visual feedback for drag operations
- **Debouncing**: Prevents rapid-fire requests
- **Offline Detection**: Connection status indicator

---

## Core Livewire 3 Best Practices

### 1. Use Modern Attributes

Livewire 3 provides powerful attributes that should be used instead of older patterns:

#### `#[Computed]` for Derived Data
```php
#[Computed]
public function items(): Collection
{
    // Expensive operations that should be cached
    return $this->expensiveQuery();
}
```

**Why**: Automatically caches results and only recomputes when dependencies change.

#### `#[Reactive]` for Parent-Child Communication
```php
#[Reactive]
public Collection $items;
```

**Why**: Automatically updates child components when parent data changes.

#### `#[On]` for Event Listeners
```php
#[On('item-updated')]
public function handleItemUpdate(): void
{
    // Handle event
}
```

**Why**: Cleaner than `$listeners` array, type-safe, and more explicit.

#### `#[Url]` for URL State Management
```php
#[Url(except: 'list')]
public string $viewMode = 'list';
```

**Why**: Automatically syncs component state with URL, enabling bookmarking and sharing.

### 2. Event Dispatching

Always use `$this->dispatch()` instead of deprecated methods:

```php
// ✅ Correct (Livewire 3)
$this->dispatch('notify', message: 'Success', type: 'success');

// ❌ Deprecated (Livewire 2)
$this->emit('notify', 'Success', 'success');
```

**Best Practices**:
- Use named parameters for clarity
- Keep event names descriptive and consistent
- Document event payloads in component comments

### 3. Loading States

Always provide visual feedback during operations:

```php
// In Blade template
<button
    wire:click="save"
    wire:loading.attr="disabled"
    wire:target="save"
>
    <span wire:loading.remove wire:target="save">Save</span>
    <span wire:loading wire:target="save">Saving...</span>
</button>
```

**Best Practices**:
- Use `wire:target` to scope loading states to specific actions
- Disable buttons during operations to prevent double-submission
- Show loading indicators that match your design system
- Use `wire:loading.class` for subtle visual feedback

### 4. Error Handling

Always wrap operations in try-catch blocks:

```php
public function updateItem(int $id): void
{
    try {
        $item = Item::findOrFail($id);
        $item->update($this->validated());

        $this->dispatch('notify',
            message: 'Item updated successfully',
            type: 'success'
        );
    } catch (\Exception $e) {
        \Log::error('Failed to update item', [
            'error' => $e->getMessage(),
            'itemId' => $id
        ]);

        $this->dispatch('notify',
            message: 'Failed to update item',
            type: 'error'
        );
    }
}
```

**Best Practices**:
- Log errors with context for debugging
- Provide user-friendly error messages
- Never expose internal error details to users
- Use appropriate exception types when possible

### 5. Component Structure

#### Single Root Element
```php
// ✅ Correct
<div>
    <!-- content -->
</div>

// ❌ Incorrect
<div>...</div>
<div>...</div>
```

#### Proper `wire:key` Usage
```php
// In loops
@foreach($items as $item)
    <div wire:key="item-{{ $item->id }}">
        <!-- content -->
    </div>
@endforeach

// For dynamic components
<livewire:child-component wire:key="child-{{ $uniqueId }}" />
```

**Best Practices**:
- Always use `wire:key` in loops
- Use unique, stable identifiers
- Include component type in key for dynamic components

### 6. State Management

#### Use Computed Properties for Derived Data
```php
#[Computed]
public function filteredItems(): Collection
{
    return $this->items->filter(fn($item) => $item->isActive);
}
```

#### Use Reactive Properties for Shared State
```php
// Parent component
public Collection $items;

// Child component
#[Reactive]
public Collection $items;
```

**Best Practices**:
- Prefer computed properties over methods for derived data
- Use reactive properties for parent-child data sharing
- Avoid storing computed values in regular properties

### 7. Performance Optimization

#### Eager Loading
```php
$items = Item::with(['tags', 'project'])->get();
```

#### Limit Computed Property Recalculations
```php
#[Computed]
public function expensiveOperation(): array
{
    // This only runs when dependencies change
    return $this->processData($this->items);
}
```

**Best Practices**:
- Always eager load relationships to prevent N+1 queries
- Use computed properties for expensive operations
- Consider pagination for large datasets
- Use `wire:loading.delay` to prevent flickering on fast operations

### 8. Accessibility (a11y)

Always make components accessible:

```php
<button
    wire:click="action"
    aria-label="Descriptive action name"
    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
>
    Action
</button>

<div role="group" aria-label="Button group">
    <!-- buttons -->
</div>

<div aria-live="polite" aria-label="Dynamic content">
    {{ $dynamicContent }}
</div>
```

**Best Practices**:
- Add `aria-label` to icon-only buttons
- Use `aria-pressed` for toggle buttons
- Use `role` attributes for semantic meaning
- Use `aria-live` for dynamic content updates
- Ensure keyboard navigation works (Tab, Enter, Space)
- Test with screen readers

### 9. User Experience (UX)

#### Loading Feedback
- Show loading states for all async operations
- Use skeleton screens for initial loads
- Provide progress indicators for long operations

#### Error Feedback
- Show user-friendly error messages
- Use toast notifications for non-critical errors
- Use inline validation for form errors

#### Optimistic Updates
```php
// Show immediate feedback, update server in background
@dragstart="
    $el.classList.add('opacity-50');
    // Server update happens via event
"
```

**Best Practices**:
- Always provide visual feedback
- Use debouncing for rapid user actions
- Implement optimistic updates where appropriate
- Show success confirmations for important actions

### 10. Transitions and Animations

Use `wire:transition` for smooth view changes:

```php
<div wire:transition="fade">
    @if($showContent)
        <!-- content -->
    @endif
</div>
```

**Best Practices**:
- Keep transitions subtle and fast (200-300ms)
- Use CSS transitions for performance
- Test transitions in both light and dark modes
- Don't overuse animations

### 11. Event-Driven Architecture

Design components to communicate via events:

```php
// Component A dispatches
$this->dispatch('item-updated', itemId: $id);

// Component B listens
#[On('item-updated')]
public function refreshItems(int $itemId): void
{
    // Handle update
}
```

**Best Practices**:
- Use events for cross-component communication
- Keep event names descriptive and consistent
- Document event contracts
- Prefer events over direct method calls between components

### 12. Validation

Always validate user input:

```php
public function save(): void
{
    $validated = $this->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email'],
    ]);

    // Use validated data
}
```

**Best Practices**:
- Validate on the server, not just client-side
- Use Form Request classes for complex validation
- Provide clear validation error messages
- Show validation errors inline

### 13. Security

#### Authorization
```php
public function delete(int $id): void
{
    $item = Item::findOrFail($id);

    // Always check authorization
    $this->authorize('delete', $item);

    $item->delete();
}
```

**Best Practices**:
- Always authorize actions, even in Livewire
- Use policies and gates
- Never trust client-side validation alone
- Sanitize user input

### 14. Testing Considerations

Design components with testing in mind:

```php
// Make methods testable
public function updateStatus(int $id, string $status): void
{
    // Logic here
}

// Test with Livewire::test()
Livewire::test(Component::class)
    ->call('updateStatus', 1, 'active')
    ->assertSet('status', 'active');
```

**Best Practices**:
- Keep business logic in methods, not in templates
- Make methods pure when possible
- Use factories for test data
- Test user interactions, not just method calls

### 15. Code Organization

#### Component Naming
- Use descriptive, action-oriented names
- Follow Laravel naming conventions
- Group related components in namespaces

#### Method Organization
```php
// 1. Properties
public string $name = '';

// 2. Lifecycle hooks
public function mount(): void {}

// 3. Event listeners
#[On('event')]
public function handleEvent(): void {}

// 4. Public actions
public function save(): void {}

// 5. Computed properties
#[Computed]
public function items(): Collection {}

// 6. Private helpers
private function helper(): void {}
```

**Best Practices**:
- Keep components focused on single responsibility
- Extract complex logic to service classes
- Use traits for shared functionality
- Document complex methods

---

## Quick Reference Checklist

When building Livewire 3 components, ensure:

- [ ] Use `#[Computed]` for derived data
- [ ] Use `#[Reactive]` for parent-child communication
- [ ] Use `#[On]` for event listeners
- [ ] Use `$this->dispatch()` for events
- [ ] Add loading states to all async operations
- [ ] Wrap operations in try-catch blocks
- [ ] Provide user feedback (success/error)
- [ ] Add ARIA labels for accessibility
- [ ] Use `wire:key` in loops
- [ ] Eager load relationships
- [ ] Validate and authorize all actions
- [ ] Test components thoroughly
- [ ] Keep components focused and organized

---

## Additional Resources

- [Livewire 3 Documentation](https://livewire.laravel.com/docs)
- [Laravel Best Practices](https://laravel.com/docs)
- [Web Accessibility Guidelines (WCAG)](https://www.w3.org/WAI/WCAG21/quickref/)
- [Alpine.js Documentation](https://alpinejs.dev/)

---

## Notes

- These practices evolve with the framework
- Always refer to official documentation for latest patterns
- Adapt practices to your team's conventions
- Prioritize user experience and accessibility
