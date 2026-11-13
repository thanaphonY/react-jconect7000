package httpapi

import (
	"bytes"
	"encoding/csv"
	"fmt"
	"github.com/go-pdf/fpdf"
	"github.com/gofiber/fiber/v2"
	"github.com/xuri/excelize/v2"
	"path/filepath"
	"sort"
	"time"
)

func (a *App) reportExport(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	if b["saveToFileManager"] == true {
		return fiber.NewError(423, "Saving report files is disabled while verifying existing production data")
	}
	rows, _, err := a.reportRows(c)
	if err != nil {
		return err
	}
	return a.exportRows(c, rows, text(b["format"]), "JConnect Report")
}
func (a *App) exportRows(c *fiber.Ctx, rows []Row, format, title string) error {
	var err error
	keys := []string{}
	if len(rows) > 0 {
		for k := range rows[0] {
			keys = append(keys, k)
		}
		sort.Strings(keys)
	}
	filename := "report_" + time.Now().In(time.FixedZone("Asia/Bangkok", 7*3600)).Format("20060102_150405")
	switch format {
	case "csv":
		var out bytes.Buffer
		out.Write([]byte{0xef, 0xbb, 0xbf})
		w := csv.NewWriter(&out)
		if err = w.Write(keys); err != nil {
			return err
		}
		for _, r := range rows {
			values := make([]string, len(keys))
			for i, k := range keys {
				values[i] = exportText(r[k])
			}
			if err = w.Write(values); err != nil {
				return err
			}
		}
		w.Flush()
		if err = w.Error(); err != nil {
			return err
		}
		c.Set("Content-Disposition", `attachment; filename="`+filename+`.csv"`)
		c.Type("csv")
		return c.Send(out.Bytes())
	case "excel", "xlsx":
		f := excelize.NewFile()
		defer f.Close()
		values := make([]any, len(keys))
		for i, k := range keys {
			values[i] = k
		}
		if err = f.SetSheetRow("Sheet1", "A1", &values); err != nil {
			return err
		}
		for n, r := range rows {
			for i, k := range keys {
				values[i] = r[k]
				if _, ok := r[k].(map[string]any); ok {
					values[i] = jsonValue(r[k])
				}
			}
			cell, _ := excelize.CoordinatesToCellName(1, n+2)
			if err = f.SetSheetRow("Sheet1", cell, &values); err != nil {
				return err
			}
		}
		out, err := f.WriteToBuffer()
		if err != nil {
			return err
		}
		c.Set("Content-Disposition", `attachment; filename="`+filename+`.xlsx"`)
		c.Type("xlsx")
		return c.Send(out.Bytes())
	case "pdf":
		pdf := fpdf.New("L", "mm", "A4", filepath.Join(a.Root, "frontend/assets/fonts"))
		pdf.AddUTF8Font("Sarabun", "", "Sarabun-Regular.ttf")
		pdf.AddPage()
		pdf.SetFont("Sarabun", "", 12)
		pdf.CellFormat(0, 10, title, "", 1, "L", false, 0, "")
		pdf.SetFont("Sarabun", "", 8)
		if len(keys) > 0 {
			width := 277.0 / float64(len(keys))
			height := 6.0
			draw := func(values []string) {
				for _, value := range values {
					rr := []rune(value)
					max := int(width / 1.8)
					if max < 2 {
						max = 2
					}
					if len(rr) > max {
						value = string(rr[:max]) + "…"
					}
					pdf.CellFormat(width, height, value, "1", 0, "L", false, 0, "")
				}
				pdf.Ln(-1)
			}
			draw(keys)
			for _, r := range rows {
				if pdf.GetY() > 185 {
					pdf.AddPage()
					draw(keys)
				}
				values := []string{}
				for _, k := range keys {
					values = append(values, exportText(r[k]))
				}
				draw(values)
			}
		}
		var out bytes.Buffer
		if err = pdf.Output(&out); err != nil {
			return err
		}
		c.Set("Content-Disposition", `attachment; filename="`+filename+`.pdf"`)
		c.Type("pdf")
		return c.Send(out.Bytes())
	default:
		return invalid("Format must be csv, excel or pdf")
	}
}
func exportText(v any) string {
	if v == nil {
		return ""
	}
	switch value := v.(type) {
	case time.Time:
		return value.In(time.FixedZone("Asia/Bangkok", 7*3600)).Format("2006-01-02 15:04:05")
	case map[string]any, []any:
		return jsonValue(v)
	default:
		return fmt.Sprint(v)
	}
}
