package httpapi

import (
	"bytes"
	"encoding/xml"
	"github.com/gofiber/fiber/v2"
	"io"
	"jconnect/go-backend/internal/service"
	"net/http"
	"os"
	"path/filepath"
	"strings"
)

func (a *App) upload(c *fiber.Ctx) error {
	kind := c.Params("kind")
	folder, module := "", ""
	switch kind {
	case "devices":
		folder, module = "devices", "devices"
	case "device-templates":
		folder, module = "device_templates", "devicetemplate"
	case "maps", "map-image":
		folder, module = "maps", "dashboard"
	case "svg", "mimics", "svg-widget":
		folder, module = "mimics", "widgets"
	default:
		return fiber.ErrNotFound
	}
	u := actor(c)
	if err := a.require(c, module); err != nil {
		return err
	}
	if !u.Admin() && !u.Can(module, "all_create") && !u.Can(module, "own_create") && !u.Can(module, "all_update") && !u.Can(module, "own_update") {
		return fiber.ErrForbidden
	}
	if !a.Write {
		return fiber.NewError(423, "Read-only verification; upload was not stored")
	}
	form, err := c.MultipartForm()
	if err != nil {
		return invalid("Choose an upload file")
	}
	var file io.ReadCloser
	var size int64
	for _, files := range form.File {
		if len(files) > 0 {
			size = files[0].Size
			file, err = files[0].Open()
			break
		}
	}
	if err != nil || file == nil {
		return invalid("Choose an upload file")
	}
	defer file.Close()
	limit := int64(10 * 1024 * 1024)
	if folder == "mimics" {
		limit = 2 * 1024 * 1024
	}
	if size > limit {
		return invalid("Upload exceeds allowed file size")
	}
	raw, err := io.ReadAll(io.LimitReader(file, limit+1))
	if err != nil {
		return err
	}
	if int64(len(raw)) > limit {
		return invalid("Upload exceeds allowed file size")
	}
	ext := ""
	if folder == "mimics" {
		raw, err = sanitizeSVG(raw)
		if err != nil {
			return invalid("Invalid or unsafe SVG")
		}
		ext = ".svg"
	} else {
		ext = map[string]string{"image/png": ".png", "image/jpeg": ".jpg", "image/gif": ".gif", "image/webp": ".webp"}[http.DetectContentType(raw)]
		if ext == "" {
			return invalid("Only PNG, JPEG, GIF or WebP images are allowed")
		}
	}
	name := service.Token() + ext
	dir := filepath.Join(a.Root, "frontend/uploads", folder)
	if err = os.MkdirAll(dir, 0755); err != nil {
		return err
	}
	if err = os.WriteFile(filepath.Join(dir, name), raw, 0644); err != nil {
		return err
	}
	return data(c, Row{"filename": name, "image": name, "path": "/api/v1/uploads/" + kind + "/" + name})
}
func sanitizeSVG(raw []byte) ([]byte, error) {
	dec := xml.NewDecoder(bytes.NewReader(raw))
	var out bytes.Buffer
	enc := xml.NewEncoder(&out)
	skip := 0
	root := false
	for {
		token, err := dec.Token()
		if err == io.EOF {
			break
		}
		if err != nil {
			return nil, err
		}
		switch t := token.(type) {
		case xml.StartElement:
			name := strings.ToLower(t.Name.Local)
			if skip > 0 {
				skip++
				continue
			}
			if name == "script" || name == "foreignobject" || name == "iframe" || name == "object" || name == "embed" || name == "style" || name == "animate" || name == "set" || name == "animatemotion" || name == "animatetransform" {
				skip = 1
				continue
			}
			if !root {
				if name != "svg" {
					return nil, xml.UnmarshalError("SVG root required")
				}
				root = true
			}
			attrs := []xml.Attr{}
			for _, attr := range t.Attr {
				k := strings.ToLower(attr.Name.Local)
				v := strings.TrimSpace(strings.ToLower(attr.Value))
				if strings.HasPrefix(k, "on") || strings.Contains(v, "javascript:") || strings.Contains(v, "vbscript:") || strings.Contains(v, "data:text/html") {
					continue
				}
				if (k == "href" || k == "src") && v != "" && !strings.HasPrefix(v, "#") && !strings.HasPrefix(v, "data:image/png;base64,") && !strings.HasPrefix(v, "data:image/jpeg;base64,") {
					continue
				}
				if k == "style" && (strings.Contains(v, "url(") || strings.Contains(v, "expression(")) {
					continue
				}
				attrs = append(attrs, attr)
			}
			t.Attr = attrs
			if err = enc.EncodeToken(t); err != nil {
				return nil, err
			}
		case xml.EndElement:
			if skip > 0 {
				skip--
				continue
			}
			if err = enc.EncodeToken(t); err != nil {
				return nil, err
			}
		case xml.Directive, xml.ProcInst:
			continue
		case xml.CharData:
			if skip == 0 {
				if err = enc.EncodeToken(t); err != nil {
					return nil, err
				}
			}
		}
	}
	if !root {
		return nil, xml.UnmarshalError("SVG root required")
	}
	if err := enc.Flush(); err != nil {
		return nil, err
	}
	return out.Bytes(), nil
}
