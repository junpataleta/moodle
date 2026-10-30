import{useEffect as r,useState as s}from"react";import{ActivityIcon as l}from"@moodlehq/design-system";import{jsx as u}from"react/jsx-runtime";/**
 * The icon of a course activity card, rendered with the design system ActivityIcon.
 *
 * Mounted from core_courseformat/local/content/cm/cmicon, which keeps a server-rendered
 * fallback inside the mount point until this component takes over. Keep the size of that
 * fallback, including its size on small screens, in sync with this component.
 *
 * @module     core_course/ActivityIcon
 * @copyright  2026 Moodle Pty Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */const o="(max-width: 575.98px)",m=()=>{const[i,n]=s(()=>window.matchMedia(o).matches);return r(()=>{const e=window.matchMedia(o),t=c=>n(c.matches);return n(e.matches),e.addEventListener("change",t),()=>e.removeEventListener("change",t)},[]),i};function d({icon:i,alt:n="",container:e="default",size:t="md",mobileSize:c=t}){const a=m();return u(l,{icon:i,alt:n,container:e,size:a?c:t})}export{d as default};
